<?php
// Chat endpoint. Secrets are loaded from /etc/mira-assistant/config.php, outside the web root.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Метод не поддерживается']); exit; }
$configPath = '/etc/mira-assistant/config.php';
if (!is_file($configPath)) { http_response_code(503); echo json_encode(['error'=>'Ассистент ещё не настроен. Администратору нужно добавить API-ключ в конфигурацию сервера.']); exit; }
$config = require $configPath;
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body) || !isset($body['messages']) || !is_array($body['messages'])) { http_response_code(400); echo json_encode(['error'=>'Некорректный запрос']); exit; }
$messages = [];
$systemPrompt = "Ты — Мира, доброжелательный и спокойный ИИ-помощник по вопросам недвижимости. Общайся на русском простым языком, без давления, навязчивой рекламы и обещаний результата. Помогаешь разобраться с покупкой первого жилья, ипотекой и первоначальным взносом, новостройками и вторичным рынком, продажей и обменом, продажей дома и покупкой квартиры, строительством и участками, инвестициями и арендой, коммерческой недвижимостью, переездом, юридическими вопросами и документами, а также когда человек пока не знает, с чего начать. Сначала ответь по существу и задай 1-2 уместных уточняющих вопроса за раз. Уточняй только то, что необходимо для ситуации: город/регион, цель, сроки, бюджет/комфортный платёж, наличие другой недвижимости или первоначального взноса, если это релевантно. Не собирай паспортные данные, точный адрес, банковские данные и иные избыточные сведения. Не придумывай ставки, цены, программы, объекты, законы или условия банков; обозначай, что актуальные условия необходимо проверить. Юридические и ипотечные ответы не заменяют консультацию квалифицированного специалиста. Не утверждай, что заявка отправлена, пока пользователь не отправил форму и сервер не подтвердил отправку. Никогда не проси телефон в чате: контакты собираются отдельной формой с явным согласием. Если уже достаточно информации или пользователь хочет связаться со специалистом, предложи передачу обращения. В конце каждого ответа добавляй отдельную последнюю строку в формате META: {\"offer_contact\":true/false,\"summary\":\"краткое резюме ситуации по известным фактам, без выдумок\"}. Ставь offer_contact=true, если пользователь явно хочет связь со специалистом или диалог уже собрал достаточно контекста; иначе false. Не показывай пользователю строку META как обычный текст.";
$messages[] = ['role'=>'system','content'=>$systemPrompt];
foreach (array_slice($body['messages'], -20) as $m) { if (!is_array($m) || !isset($m['role'],$m['content']) || !in_array($m['role'],['user','assistant'],true) || !is_string($m['content'])) continue; $messages[]=['role'=>$m['role'],'content'=>mb_substr($m['content'],0,2500)]; }
if (count($messages) < 2) { http_response_code(400); echo json_encode(['error'=>'Напишите вопрос Мире']); exit; }
$payload=['model'=>$config['openai_model'] ?? 'gpt-4o-mini','messages'=>$messages,'temperature'=>0.5,'max_tokens'=>700];
$ch=curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$config['openai_api_key']],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>45]);
$result=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch);curl_close($ch);
if ($result===false || $status<200 || $status>=300) { error_log('Mira OpenAI error HTTP '.$status.' '.$curlError.' '.substr((string)$result,0,500)); http_response_code(502); echo json_encode(['error'=>'Не удалось получить ответ от Миры. Попробуйте ещё раз немного позже.']); exit; }
$json=json_decode($result,true);$content=$json['choices'][0]['message']['content'] ?? '';
if (!$content) { http_response_code(502); echo json_encode(['error'=>'Получен пустой ответ. Попробуйте переформулировать вопрос.']); exit; }
$offer=false;$summary='';
if (preg_match('/META:\s*(\{.*\})\s*$/us',$content,$match)) { $meta=json_decode($match[1],true); if(is_array($meta)){ $offer=($meta['offer_contact']??false)===true; $summary=mb_substr((string)($meta['summary']??''),0,1200); } $content=trim(substr($content,0,strlen($match[0])*-1)); }
$content=preg_replace('/\n?META:\s*\{.*\}\s*$/us','',trim($content));
echo json_encode(['reply'=>trim($content),'offer_contact'=>$offer,'summary'=>$summary],JSON_UNESCAPED_UNICODE);
