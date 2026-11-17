<?php
return [
 'name'=>env('APP_NAME','GlamFinanças'),'env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','http://localhost:8088'),
 'timezone'=>'America/Belem','locale'=>'pt_BR','fallback_locale'=>'pt_BR','faker_locale'=>'pt_BR',
 'key'=>env('APP_KEY'),'cipher'=>'AES-256-CBC','maintenance'=>['driver'=>'file'],
];
