<?php

namespace Leit040\AspasiaBot\Controllers;

use http\Client;
use Illuminate\Http\Request;

class BotController
{

    public function callback(Request $request){


//        $client = new \GuzzleHttp\Client();
//        $response =  $client->get('https://api.telegram.org/bot5004217511:AAHDI32ciQvcHVk9EII4lsBNTU1JBPgHU8w/getUpdates');
//       $json1 = $response->getBody()->getContents();
     //  $json = $request->json();

      //  $json = json_decode($json,true);
       $json = json_decode(file_get_contents('php://input'), true);
//       file_put_contents('./tmp/test.txt',$json['message']['text'], FILE_APPEND );
//       file_put_contents('./tmp/test.txt','_!!!!!!!_',FILE_APPEND);
        $data['chat_id'] = $json['message']['chat']['id'];
        $data['text'] = "Your order (#" . $json['message']['text'] . ") now have  status  '" . '!!!!!!' . "'";

        file_get_contents('https://api.telegram.org/bot' . getenv("API_TOKEN") . '/sendMessage?' . http_build_query($data));

    }


}
//Lsn07fyM3rl91V5JYA