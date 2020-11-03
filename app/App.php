<?php
namespace App;

use App\TwitchChatClient;
use Calcinai\PHPi\Pin\PinFunction;
use App\LootGame;
use App\GameD100;

class App
{
    public $twitch_user = TWITCH_USER;
    public $twitch_oauth = TWITCH_AUTH;
    private $client;
    private $vitoria = 0;
    private $derrota = 0;
    private $mortes = 0;
    private $lootGame;
    private $gameD100;    

    /**
     * App constructor.
     * @param array $config
     */
    public function __construct(){
        echo "Iniciando o chatbot...".PHP_EOL;

        $twitch_user = $this->twitch_user;
        $twitch_oauth = $this->twitch_oauth;

        if (!$twitch_user or !$twitch_oauth) {
            echo "Falta informar 'twitch_user' e/ou 'twitch_oauth' nas configurações.".PHP_EOL;
            return;
        }

        $this->client = new TwitchChatClient($twitch_user, $twitch_oauth);
        $this->client->connect();

        if (!$this->client->isConnected()) {
            echo "Não foi possível conectar.".PHP_EOL;
            return;
        }

        echo "Conectado.".PHP_EOL;
        $this->lootGame = new LootGame;
        $this->gameD100 = new GameD100;

        while (true) {
            $content = $this->client->read(512);

            //is it a ping?
            if (strstr($content, 'PING')) {
                $this->client->send('PONG :tmi.twitch.tv');
                continue;
            }

            //is it an actual msg?
            if (strstr($content, 'PRIVMSG')) {
                $return = $this->printMessage($content);
                $this->commands(trim($return['msg']), $return['nick']);
                continue;
            }

            sleep(5);
        }
    }

    public function printMessage($raw_message)
    {
        $parts = explode(":", $raw_message, 3);
        $nick_parts = explode("!", $parts[1]);

        $nick = $nick_parts[0];
        $message = $parts[2];

        echo $nick;
        echo ': ';
        echo $message;
        echo PHP_EOL;

        return ['msg' => $message, 'nick' => $nick];
    }

    public function sendMessage($msg)
    {
        $this->client->send('PRIVMSG #' . $this->client::$channel . ' :' . $msg);
    }

    public function commands($msg, $user)
    {
        switch ($msg) {
                // Raspberry Pi
            case '!piscarled':
                $board = \Calcinai\PHPi\Factory::create();
                $pin = $board->getPin(17); //BCM pin number
                $pin->setFunction(PinFunction::OUTPUT);
                $pin->high();
                sleep(2);
                $pin->low();
                break;
            case '!movermotor':
                $codigopython = "motor.py";
                shell_exec('python3 ' . $codigopython);
                $this->sendMessage('@' . $user . ' você acionou o motor!');
                break;
                // FIM Raspberry Pi
            case '!roll20':
                $roll = rand(1, 20);
                $this->sendMessage('@' . $user . ' o resultado do seu D20 é ' . $roll);
                break;
            case '!vitoria':
                $this->sendMessage('Estamos com ' . $this->vitoria . ' vitórias!');
                break;
            case '!derrota':
                $this->sendMessage('Estamos com ' . $this->vitoria . ' derrotas!');
                break;
            case '!mortes':
                $this->sendMessage('Estamos com ' . $this->vitoria . ' mortes!');
                break;
            case '!vitoria++':
                $this->vitoria++;
                $this->sendMessage('Mais uma vitória adicionada! Show!');
                break;
            case '!derrota++':
                $this->derrota++;
                $this->sendMessage('Mais uma derrota? Melhor encerrar por hoje.');
                break;
            case '!mortes++':
                $this->mortes++;
                $this->sendMessage('Mais uma morte adicionada!');
                break;
            case '!salve':
                $this->sendMessage('@' . $user . ' salve salve! <3');
                break;
            case '!comandos':
                $this->sendMessage('Atualmente os comandos são: roll20, roll100, salve');
                break;
                // Game D100  
            case '!roll100':
                $roll = rand(1, 100);
                if ($this->gameD100->ativo) {
                    $msg = $this->gameD100->adicionarD100($user, $roll);
                } else {
                    $msg = 'Jogo d100 não está ativado!';
                }
                $this->sendMessage($msg);
                break;
            case '!gamed100 iniciar':
                if ($user == ADMIN_USER) {
                    $this->gameD100->resetRanking();
                    $this->sendMessage('Jogo d100 iniciado! Façam suas rolagens!');
                    $this->gameD100->ativo = true;
                }
                break;
            case '!gamed100 encerrar':
                if ($user == ADMIN_USER) {
                    $this->sendMessage('Jogo d100 finalizado!');
                    $this->gameD100->ativo = false;
                    $msg = $this->gameD100->finalizarJogo();
                    $this->sendMessage($msg);
                }
                break;
            // Loot Game
            case '!loot':
                $msg = $this->lootGame->receberLoot($user);
                $this->sendMessage($msg);
                break;
            case '!pontos':
                $msg = $this->lootGame->consultarPontos($user);
                $this->sendMessage('@' . $user . $msg);
                break;
            case '!ranking':
                $msg = $this->lootGame->verificarRanking();
                $this->sendMessage($msg);
                break;
            case '!inventario':
                $msg = $this->lootGame->consultarInventario($user);
                $this->sendMessage('@' . $user . $msg);
                break;
        }

        if (preg_match('/!canal \w+/', $msg)) {
            if ($user == 'felipez3r0') {
                $canal = explode(' ', $msg)[1];
                $this->sendMessage('!sh-so ' . $canal);
            }
        }
    }    
    
}