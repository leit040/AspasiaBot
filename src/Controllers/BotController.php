<?php

namespace Leit040\AspasiaBot\Controllers;


use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;


class BotController
{

    private Client $client;
    private DbRepository $dbr;
    private string $adminId;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => 'https://api.telegram.org/bot' . getenv("TOKEN") . '/', 'timeout' => 2.0]);
        $this->dbr = new DbRepository();
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    function getUpdates()
    {

        $this->dbr->getMastersList();
        $response = $this->client->request('GET', 'getUpdates');
        $mes = json_decode($response->getBody()->getContents(), true);
        $messages = $mes['ok'] ? array_map(fn($item) => $item['message'], $mes['result']) : false;
        $this->callback($messages);
    }


    public function callback(array $messages = null)
    {
        if ($messages) {
            $json = $messages;
        } else {
            $json = \request()->json()->all();
        }
        foreach ($messages as $message) {
            $this->action($message);
        }
    }

    private function action($message)
    {
        if (isset($message['callback_query'])) {
            switch ($message['callback_query']['message']['data']) {
                case '/next':
                    $this->sendMasterList($message['callback_query']['from']['id']);
                    return;

                default:
                 if ($masterId = $this->dbr->isMasterId($message['callback_query']['message']['data']))
                 {

                 }
                    break;
            }

        }

        switch ($message['text']) {
            case 'MASTER':
                $this->dbr->saveUser($message, 3);
                return;
            case '/start':
                $this->dbr->saveUser($message);
                $this->sendHello($message['from']['id']);
                return;
            case '/finish':
                $this->finishDialog($message['from']['id']);
                    return;
            default:
                if ($chat_id = $this->dbr->ifMasterInDialog($message['from']['id'])) {
                    $this->forwardMessage($chat_id, $message, 'masterToUser');
                    return;
                }
                if ($chat_id = $this->dbr->ifClientInDialog($message['from']['id'])) {
                    $this->forwardMessage($chat_id, $message, 'userToMaster');
                    return;
                }
        }

    }


    public function sendMessage(array $data)
    {
        $response = $this->client->request('GET', "sendMessage?" . http_build_query($data));
    }

public function startDialog($chat_id, $master_id){
        $this->dbr->saveDialog($chat_id, $master_id);
    $data = [
        'chat_id' => $chat_id,
        'text' => "Мастер получил ваше сообщение, и скоро вам ответит",
        ];
    $this->sendMessage($data);
    }

    public function sendMasterList($chat_id)
    {
        $rows = $this->dbr->getMastersList();
        $buttons = array_map(fn($row) => ['text' => $row['name'] . " " . $row['lastname'], 'callback_data' => $row['user_id']]
            , $rows);
        $data = [
            'chat_id' => $chat_id,
            'text' => "Выберите интересующего вас мастера:",
            'reply_markup' => json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [$buttons],
                ]
            ])
        ];

        $this->sendMessage($data);

    }

    public function sendHello($userId)
    {
        $data = [
            'chat_id' => $userId,
            'text' => "Привет. Это чат-бот для поддержки клиентов. Нажмите \"Продолжить\", выберите мастера и напишите свой вопрос",
            'reply_markup' => json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => [
                    [
                        ['text' => 'Продолжить', 'callback_data' => '/next'],
                        ['text' => 'Выход', 'callback_data' => '/end']
                    ],
                ]
            ])
        ];

        $this->sendMessage($data);
    }

    public function finishDialog($masterId){
        $clientId = $this->dbr->ifClientInDialog($masterId);
        $this->dbr->finishDialog($masterId);
        $data = [
            'chat_id' => $clientId,
            'text' => "Мастер закончил этот разговор. Спасибо за Ваше обращение.",
            'inline_keyboard' => [
                [
                    ['text' => 'Продолжить', 'callback_data' => '/start'],

                ],
            ]
        ];
        $this->sendMessage($data);

    }

    public function forwardMessage($message, $userId, $mark)
    {
        $senderName = $this->dbr->getNameByID($userId);
        switch ($mark) {
            case 'masterToUser':
                $mess = "Мастер " . $message['from']['name'] . " " . $message['from']['name'] . " пишет клиенту " . $senderName . " :" . $message['text'];
                break;
            case 'userToMaster':
                $mess = "Клиент " . $message['from']['name'] . " " . $message['from']['name'] . " пишет мастеру " . $senderName . " :" . $message['text'];
                break;

        }
        $data = [
            'chat_id' => $userId,
            'text' => $message['text'],
            'keyboard' => $mark== 'userToMaster'?[[['text' => '/finish']]]:''
        ];
        $this->sendMessage($data);

        $dataToAdmin = [
            'chat_id' => $this->adminId,
            'text' => $mess,
        ];
        $this->sendMessage($dataToAdmin);
    }
}
