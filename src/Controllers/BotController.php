<?php

namespace Leit040\AspasiaBot\Controllers;


use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;


class BotController
{

    private Client $client;
    private DbRepository $dbr;
    private string $adminId = '365124248';
    private bool $isManual = false;

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
        $this->isManual = true;
        $this->dbr->getMastersList();
        $response = $this->client->request('GET', 'getUpdates');
        $mes = json_decode($response->getBody()->getContents(), true);
        $this->callback($mes); //Вызываем основной обработчик
    }


    public function callback(array $mes = null)
    {

        if ($mes) {
            $updates = $mes;
        } else {
            $updates = \request()->json()->all();
        }
        $update_id = 0;
        // dd($updates['result']);
        foreach ($updates['result'] as $update) {

            $update_id = $update['update_id'];
            $this->action($update);
        }
        if ($this->isManual) {
            $this->client->request('GET', 'getUpdates?offset=' . ++$update_id);
        }
    }

    private function action($update)
    {

        if (isset($update['callback_query'])) {

            switch ($update['callback_query']['data']) {
                case '/next':
                    $this->sendMasterList($update['callback_query']['from']['id']);
                    return;

                default:

                    if ($masterId = $this->dbr->isMasterId($update['callback_query']['data'])) {
                        $this->startDialog($update['callback_query']['from']['id'], $update['callback_query']['data']);
                    }
                    break;
            }
            return;
        }
        switch ($update['message']['text']) {
            case getenv('MASTER_CODE_STRING'):
                $this->dbr->saveMaster($update['message']);
                return;
            case '/start':
                if (!$this->dbr->isMasterId($update['message']['from']['id'])) {
                    $this->dbr->saveUser($update['message']);
                    $this->sendHello($update['message']['from']['id']);
                }
                return;

            case '/finish':
                if ($this->dbr->ifMasterInDialog($update['message']['from']['id'])) {
                    $this->finishDialog($update['message']['from']['id']);
                }
                return;
            default:

                if ($chat_id = $this->dbr->ifMasterInDialog($update['message']['from']['id'])) {

                    $this->forwardMessage($update['message'], $chat_id, 'masterToUser');
                    return;
                }
                if ($chat_id = $this->dbr->ifClientInDialog($update['message']['from']['id'])) {

                    $this->forwardMessage($update['message'], $chat_id, 'userToMaster');
                    return;
                }
        }

    }


    public function sendMessage(array $data)
    {
        $response = $this->client->request('GET', "sendMessage?" . http_build_query($data));
    }

    public function startDialog($chat_id, $master_id)
    {

        $this->dbr->saveDialog($chat_id, $master_id);
        $data = [
            'chat_id' => $chat_id,
            'text' => "Мастер получил ваше сообщение, и скоро вам ответит",
        ];
        $this->sendMessage($data);
        $data = [
            'chat_id' => $master_id,
            'text' => "С вами хочет побеседовать пользователь. Тут будут сообщения от него. Чтобы закончить диалог введите команду /finish",
        ];
        $this->sendMessage($data);
    }

    public function sendMasterList($chat_id)
    {
        $rows = $this->dbr->getMastersList();
        $buttons = array_map(fn($row) => [['text' => $row['name'] . " " . $row['lastname'], 'callback_data' => $row['user_id']]]
            , $rows);

        $data = [
            'chat_id' => $chat_id,
            'text' => "Выберите интересующего вас мастера:",
            'reply_markup' => json_encode([
                'resize_keyboard' => true,
                'inline_keyboard' => $buttons,
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

    public function finishDialog($masterId)
    {
        $clientId = $this->dbr->ifMasterInDialog($masterId);
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
        switch ($mark) {
            case 'masterToUser':
                $clientName = $this->dbr->getNameByID($userId);
                $mess = "Мастер " . $message['from']['first_name'] . " " . $message['from']['last_name'] . " пишет клиенту " . $clientName . " :" . $message['text'];
                break;
            case 'userToMaster':
                $masterName = $this->dbr->getMasterByChatId($userId);
                $mess = "Клиент " . $message['from']['first_name'] . " " . $message['from']['last_name'] . " пишет мастеру " . $masterName . " :" . $message['text'];
                break;

        }

        $data = [
            'chat_id' => $userId,
            'text' => $message['text'],
            'keyboard' => $mark == 'userToMaster' ? [[['text' => '/finish']]] : ''
        ];
        $this->sendMessage($data);

        $dataToAdmin = [
            'chat_id' => $this->adminId,
            'text' => $mess,
        ];
        $this->sendMessage($dataToAdmin);
    }
}
