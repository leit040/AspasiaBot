<?php

namespace Leit040\AspasiaBot\Controllers;

use http\Client;
use Illuminate\Http\Request;

class BotController
{



    public function callback(Request $request){


        $json = json_decode(file_get_contents('php://input'), true);

        file_put_contents('tmp/test.txt','$json: '.print_r($json,1)."\n");


        $data = [
            'chat_id'=> $json['message']['chat']['id'],
            'text' =>  "Привет. Это чат-бот для поддержки клиентов. Нажмите \"Start\", выберите мастера и напишите свой вопрос",
            'reply_markup'=> json_encode([
                'resize_keyboard'=>true,
                'keyboard' => [

                        [
                            ['text'=>'text1','callback_data' => '/yes'],
                            ['text'=>'text2']
                        ],
                        [
                            ['text'=>'text3'],
                            ['text'=>'text4']
                        ]
                ]
            ])
        ];



         $this->sendMessage($data);
        //file_get_contents('https://api.telegram.org/bot' . getenv("TOKEN") . '/sendMessage?' . http_build_query($data));

    }

public function sendMessage(array $data){

    file_get_contents(getenv('URL').getenv('TOKEN')."/sendMessage?".http_build_query($data));

}
public function sendMasterList($chat_id){
      $dbr = new DbRepository();
      $row = $dbr->getMastersList();

    }

}

