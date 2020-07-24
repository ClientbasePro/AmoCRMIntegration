<?php

  // Интеграция CRM Clientbase с AmoCRM
  // https://ClientbasePro.ru
  // https://www.amocrm.ru/developers/
  
require_once 'common.php'; 


  // возвращает токен для авторизации и запросов
function GetAmoCRMToken() {
  if (!defined('AMOCRM_URL') || !defined('AMOCRM_INTEGRATION_ID') || !defined('AMOCRM_SECRET') || !defined('AMOCRM_TOKEN_TABLE') || !defined('AMOCRM_TOKEN_FIELD_ACCESS_TOKEN') || !defined('AMOCRM_TOKEN_FIELD_REFRESH_TOKEN') || !defined('AMOCRM_TOKEN_FIELD_TOKEN_TYPE') || !defined('AMOCRM_TOKEN_FIELD_EXPIRES_IN')) return false;
  $now = date("Y-m-d H:i:s");
    // сначала пробуем получить токен из таблицы токенов
  $e = sql_fetch_assoc(data_select_field(AMOCRM_TOKEN_TABLE, 'f'.AMOCRM_TOKEN_FIELD_ACCESS_TOKEN.' AS token, f'.AMOCRM_TOKEN_FIELD_REFRESH_TOKEN.' AS refresh', "status=0 AND f".AMOCRM_TOKEN_FIELD_ACCESS_TOKEN."<>'' AND f".AMOCRM_TOKEN_FIELD_EXPIRES_BEFORE.">'".$now."' ORDER BY f".AMOCRM_TOKEN_FIELD_EXPIRES_BEFORE." DESC LIMIT 1"));
  if ($e['token']) {
      // дополнительно проверяем авторизацию по нему
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => AMOCRM_URL.'/api/v4/account',
      CURLOPT_HTTPHEADER => array('Authorization: Bearer '.$e['token'], 'Content-Type: application/json'),
      CURLOPT_RETURNTRANSFER => true
    ));
    if ($response=curl_exec($curl)) {
      $answer = json_decode($response, true);
      if ($answer['id']) { curl_close($curl); return $e['token']; }
    }
    curl_close($curl);
  }
    // если токен из таблицы не прошёл авторизацию, то запрашиваем у Амо через refresh_token
  if ($e['refresh']) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => AMOCRM_URL.'/oauth2/access_token',
      CURLOPT_HTTPHEADER => array("Content-type: application/json"),
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => '{"client_id":"'.AMOCRM_INTEGRATION_ID.'","client_secret": "'.AMOCRM_SECRET.'","grant_type":"refresh_token","refresh_token": "'.$e['refresh'].'","redirect_uri":"https://'.$_SERVER['HTTP_HOST'].'"}'
    ));
      // получаем сам токен
    if ($response=curl_exec($curl)) $answer = json_decode($response, true);
    curl_close($curl);
    if ($answer['access_token'] && $answer['refresh_token'] && $answer['token_type'] && 0<$answer['expires_in']) { 
      data_insert(AMOCRM_TOKEN_TABLE, EVENTS_ENABLE, ['f'.AMOCRM_TOKEN_FIELD_ACCESS_TOKEN=>$answer['access_token'], 'f'.AMOCRM_TOKEN_FIELD_REFRESH_TOKEN=>$answer['refresh_token'], 'f'.AMOCRM_TOKEN_FIELD_TOKEN_TYPE=>$answer['token_type'], 'f'.AMOCRM_TOKEN_FIELD_EXPIRES_IN=>$answer['expires_in']]); 
      return $answer['access_token']; 
    }
  }  
  return false;
}
