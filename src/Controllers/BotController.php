<?php

namespace Leit040\AspasiaBot\Controllers;


use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;


class BotController
{

    private Client $client;
    private DbRepository $dbr;
    private $adminId;

    private bool $isManual = false;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => 'https://api.telegram.org/bot' . getenv("TOKEN") . '/', 'timeout' => 2.0]);
        $this->dbr = new DbRepository();
        $this->adminId = getenv("MASTER_ID");
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */

    function getUpdates()
    {
        $this->isManual = true;
        $response = $this->client->request('GET', 'getUpdates');
        $mes = json_decode($response->getBody()->getContents(), true);
        foreach ($mes as $update) {
            $this->callback($update); //Вызываем основной обработчик
        }
    }


    public function callback($mes = null)
    {
        if ($mes) {
            $update = $mes;
        } else {
            $update = \request()->json()->all();
        }
        $update_id = $update['update_id'];
        $this->action($update);

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
                        if (!$this->dbr->ifClientInActiveDialog($update['callback_query']['from']['id'])) {
                            $this->startDialog($update['callback_query']['from']['id'], $update['callback_query']['data'], '');
                        }
                    }
                    break;
            }
            return;
        }
        switch ($update['message']['text']) {
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

                if (str_contains($update['message']['text'], getenv('MASTER_CODE_STRING'))) {
                    $this->dbr->saveMaster($update['message']);
                    return;
                }
                if ($chat_id = $this->dbr->ifMasterInDialog($update['message']['from']['id'])) {
                    $this->forwardMessage($update['message'], $chat_id, 'masterToUser');
                    return;
                }
                if ($chat_id = $this->dbr->ifClientInActiveDialog($update['message']['from']['id'])) {
                    $this->forwardMessage($update['message'], $chat_id, 'userToMaster');
                    return;
                }
                $dialog_ids = $this->dbr->ifClientInPendingDialog($update['message']['from']['id']);
                if (count(($dialog_ids))) {
                    $this->dbr->savePendingMessage($update['message']['text'], $dialog_ids['id'], $dialog_ids['master_id']);
                    return;
                }

        }

    }


    public function sendMessage(array $data)
    {
        $response = $this->client->request('GET', "sendMessage?" . http_build_query($data));
    }

    public function startDialog($chat_id, $master_id, $message)
    {

        $dialogStatus = $this->dbr->saveDialog($chat_id, $master_id, $message);
        $data = [
            'chat_id' => $chat_id,
            'text' => "Напишите ваше сообщение. мастер ответит как только будет свободен.",
        ];
        $this->sendMessage($data);
        if ($dialogStatus == 'active') {
            $data = [
                'chat_id' => $master_id,
                'text' => "С Вами хочет побеседовать пользователь. Тут будут сообщения от него. Чтобы закончить диалог введите команду /finish",
            ];
            $this->sendMessage($data);
        }
    }

    public function sendMasterList($chat_id)
    {
        $rows = $this->dbr->getMastersList();
        $buttons = array_map(function ($row) {
            $masterName = $row['nameFrom'] ?? $row['name'] . " " . $row['lastname'];
            return [['text' => $masterName, 'callback_data' => $row['user_id']]];
        }, $rows);

        $data = [
            'chat_id' => $chat_id,
            'text' => "Выберите интересующего Вас мастера:",
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
        $data = [
            'chat_id' => $clientId,
            'text' => "Мастер закончил этот разговор. Спасибо за Ваше обращение.",
            'reply_markup' => json_encode([
                'resize_keyboard' => true,
                'keyboard' =>  [[['text' => '/start']]
                ]])
        ];
        $this->sendMessage($data);
        $pendingMessages = $this->dbr->finishDialog($masterId);
        if (count($pendingMessages)) {
            $data = [
                'chat_id' => $masterId,
                'text' => 'Еще один клиент желает пообщаться с вами. Вы в чате с ним. введите команду /finish после окончания диалога',
            ];
            $this->sendMessage($data);
        }
        foreach ($pendingMessages as $pendingMessage) {
            $data = [
                'chat_id' => $pendingMessage['masterId'],
                'text' => $pendingMessage['message'],
            ];
            $this->sendMessage($data);
            $this->dbr->deletePendingMessage($pendingMessage('id'));
        }

    }

    public function forwardMessage($message, $userId, $mark)
    {
        switch ($mark) {
            case 'masterToUser':
                $clientName = $this->dbr->getNameByID($userId);
                $masterName = $this->dbr->getNameByID($message['from']['id']);
                $mess = "Мастер " .$masterName . " пишет клиенту " . $clientName . " :" . $message['text'];
                $messToClient = $masterName. ': ' . PHP_EOL . $message['text'];
                break;
            case 'userToMaster':
                $masterName = $this->dbr->getMasterByChatId($userId);
                $mess = "Клиент " . $message['from']['first_name'] . " " . $message['from']['last_name'] . " пишет мастеру " . $masterName . " :" . $message['text'];
                break;

        }

        $data = [
            'chat_id' => $userId,
            'text' => $mark=="masterToUser"? $messToClient: $message['text'],
            'reply_markup' => json_encode([
                'resize_keyboard' => true,
                'keyboard' =>  [[['text' => '/finish']]
        ]])
            ];
        $this->sendMessage($data);

        $dataToAdmin = [
            'chat_id' => $this->adminId,
            'text' => $mess,
        ];
        $this->sendMessage($dataToAdmin);
    }
}
