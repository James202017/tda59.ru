<?php
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Метод не поддерживается']); exit; }
$configPath='/etc/mira-assistant/config.php';
if (!is_file($configPath)) { http_response_code(503); echo json_encode(['error'=>'Отправка заявок ещё не настроена. Администратору нужно подключить Telegram в конфигурации сервера.']); exit; }
$config=require $configPath;$data=json_decode(file_get_contents('php://input'),true);
if (!is_array($data) || empty($data['consent'])) { http_response_code(400); echo json_encode(['error'=>'Для отправки обращения необходимо согласие на обработку персональных данных.']); exit; }
$name=trim((string)($data['name']??''));$phone=trim((string)($data['phone']??''));$city=trim((string)($data['city']??''));$time=trim((string)($data['contact_time']??'Не указано'));
if ($name==='' || mb_strlen($name)>100 || $phone==='' || mb_strlen($phone)>30 || !preg_match('/^[+0-9()\-\s]{6,30}$/',$phone)) { http_response_code(400); echo json_encode(['error'=>'Проверьте имя и номер телефона.']); exit; }
$summary=mb_substr(trim((string)($data['summary']??'')),0,1500);$conversation=is_array($data['conversation']??null)?array_slice($data['conversation'],-20):[];
$lines=['НОВОЕ ОБРАЩЕНИЕ — МИРА','Имя: '.$name,'Телефон: '.$phone,'Город: '.($city!==''?$city:'не указан'),'Удобное время/способ связи: '.$time,'','Резюме: '.($summary!==''?$summary:'не сформировано'),'','Фрагмент диалога:'];
foreach($conversation as $item){ if(!is_array($item))continue;$role=($item['role']??'')==='assistant'?'Мира':'Клиент';$text=trim((string)($item['content']??''));if($text!=='')$lines[]=$role.': '.mb_substr($text,0,1200); }
$lines[]='';$lines[]='Клиент подтвердил согласие на обработку персональных данных через форму сайта.';$message=implode("\n",$lines);
if (empty($config['telegram_bot_token']) || empty($config['telegram_chat_id'])) { http_response_code(503); echo json_encode(['error'=>'Telegram ещё не настроен. Обращение не отправлено.']); exit; }
$ch=curl_init('https://api.telegram.org/bot'.$config['telegram_bot_token'].'/sendMessage');
curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode(['chat_id'=>$config['telegram_chat_id'],'text'=>$message,'disable_web_page_preview'=>true],JSON_UNESCAPED_UNICODE),CURLOPT_TIMEOUT=>20]);
$result=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$json=json_decode((string)$result,true);
if($result===false||$status<200||$status>=300||empty($json['ok'])){error_log('Mira Telegram error HTTP '.$status.' '.$err.' '.substr((string)$result,0,500));http_response_code(502);echo json_encode(['error'=>'Telegram не подтвердил отправку. Обращение не считается отправленным; попробуйте позже.']);exit;}
echo json_encode(['ok'=>true,'message'=>'Обращение передано специалисту'],JSON_UNESCAPED_UNICODE);
