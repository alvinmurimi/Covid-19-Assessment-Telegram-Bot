<?php
namespace App\Http\Conversations;

require 'vendor/autoload.php';

use BotMan\BotMan\BotMan;
use BotMan\BotMan\Messages\Message;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Keyboard;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Actions\Button;
use BotMan\BotMan\Messages\Conversations\Conversation;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Cache\SymfonyCache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
#covidassessmentbot
$config = [
    'telegram' => [
      'token' => '1118649975:AAFoonLaKcDr8i9WcqXAC6TXgCrrV_QpB-I'
    ],
    'botman' => [
    'conversation_cache_time' => 30
    ]
  ];

$driver = DriverManager::loadDriver(\BotMan\Drivers\Telegram\TelegramDriver::class);
$imagedriver = DriverManager::loadDriver(\BotMan\Drivers\Telegram\TelegramPhotoDriver::class);
$videodriver = DriverManager::loadDriver(\BotMan\Drivers\Telegram\TelegramVideoDriver::class);
$audiodriver = DriverManager::loadDriver(\BotMan\Drivers\Telegram\TelegramAudioDriver::class);
$filedriver = DriverManager::loadDriver(\BotMan\Drivers\Telegram\TelegramFileDriver::class);

$adapter = new FilesystemAdapter();
$botman = BotManFactory::create($config, new SymfonyCache($adapter));

$botman->hears('/start', function($bot) {
    $bot->startConversation(new OnboardingConversation);
});;
$botman->hears('/stop', function($bot) {
    $bot->stopsConversation();
});;
class OnboardingConversation extends Conversation
{
    protected $firstname;
    protected $email;
    protected $age;
    protected $gender;
    protected $data;
    protected $items;
    protected $stuff;
    protected $id;
    protected $evidence;

    public function askFirstname()
    {
        $this->ask('Hello! What is your name?', function(Answer $answer) {
            $this->firstname = $answer->getText();
            $this->diagnose();
        });
    }

    public function diagnose()
    {
        $this->ask('How old are you?', function(Answer $answer) {
            $this->age = $answer->getText();
            if(filter_var($this->age, FILTER_VALIDATE_INT) == FALSE){
                $this->say('Can we get serious for once, '.$this->firstname);
                $this->repeat();
            }else{
              $this->data['age'] = intval($this->age);
              $this->askGender();
            }
        });
    }

    public function askGender()
        {
            $question = Question::create("What is your gender?")
                ->fallback('Unable to ask question')
                ->callbackId('ask_gender')
                ->addButtons([
                    Button::create('Male')->value('male'),
                    Button::create('Female')->value('female'),
                ]);

            return $this->ask($question, function (Answer $answer) {
                if ($answer->isInteractiveMessageReply()) {
                    if ($answer->getValue() === 'male') {
                        $this->data['sex'] = "male";
                        $this->submit();
                    } else {
                        $this->data['sex'] = "female";
                        $this->submit();
                    }
                }
            });
    }

    public function genKeyboard($data){
      $key = [];
      foreach($data as $d){
        $name = $d['name'];
        array_push($key,array($name));
      }
      return $key;
    }

    public function single($q,$id){
      $this->id = $id;
        $quest = Question::create($q)
                ->callbackId('single')
                ->addButtons([
                    Button::create('Yes')->value('present'),
                    Button::create('No')->value('absent'),
                ]);
                $this->ask($quest, function(Answer $answer) {
                  if ($answer->isInteractiveMessageReply()) {
                      $choice = $answer->getValue();
                      $arr = array('id'=>$this->id,'choice_id'=>$choice);
                      array_push($this->data['evidence'],$arr);
                          };
                      $this->submit();
                  });
                      
      }

    public function find($arr,$needle){
        foreach($arr as $ar){
          $stuff = array_search($needle,$ar);
          if(strlen($stuff) > 0){
              return $ar['id'];
          }
        }
    }


    public function groupSingle($question,$choice)
    {
      $keyboard = $this->genKeyboard($choice);
      $this->ask($question,
          function (Answer $answer) {
              $ans = $answer->getText();
              $id = $this->find($this->stuff,$ans);
              $array = array('id'=>$id,'choice_id'=>'present');
              array_push($this->data['evidence'],$array);
              $this->submit();
            }, ['reply_markup' => json_encode([
                'keyboard' => $keyboard,
                'one_time_keyboard' => true,
                'resize_keyboard' => true
              ])]
          );
    }

      public function groupMultiple($items,$question){
        $this->stuff = $items;
        $this->items = count($items);
        $this->ask($question, function(Answer $answer) {
              $choice = $answer->getText();
              $choice = str_replace(' ', '', $choice);
              if(filter_var($choice, FILTER_VALIDATE_INT) == TRUE AND intval($choice) !== 0){
                if($choice <= count($this->stuff)){
                  $id = $this->stuff[$choice-1]['id'];
                  $arr = array('id'=>$id,'choice_id'=>'present');
                  array_push($this->data['evidence'],$arr);
                  unset($this->stuff[$choice-1]);
                  $array = array_values($this->stuff);
                  foreach($array as $stuff){
                    $id = $stuff['id'];
                    $arr = array('id'=>$id,'choice_id'=>'absent');
                    array_push($this->data['evidence'],$arr);
                  }
                  $this->submit();
                }
                else{
                  $this->say("That was an invalid response. Let's try again.");
                  $this->repeat();
                }
              }
              else{
                if(strtolower($choice) == "none"){
                  foreach($this->stuff as $array){
                    $id = $array['id'];
                    $arr = array('id'=>$id,'choice_id'=>'absent');
                    array_push($this->data['evidence'],$arr);
                  }
                  $this->submit();
                }else{
                  if($choice == "0"){
                    $this->say("wtf was that?");
                    $this->repeat();
                  }
                  $re = '/^\d+(?:,\d+)*$/';
                  if(preg_match($re,$choice)){
                    $ids = array_unique(explode(",", $choice));
                    if(count($ids) > $this->items){
                      $this->say("That didn't sound right, are you sure you sent me the correct choices?");
                      $this->repeat();
                    }else{
                      foreach($ids as $id){
                        $aid = $this->stuff[$id-1]['id'];
                        $arr = array('id'=>$aid,'choice_id'=>'present');
                        array_push($this->data['evidence'],$arr);
                    }
                    foreach($ids as $id){
                      unset($this->stuff[$id-1]);
                    }
                    $array = array_values($this->stuff);
                    foreach($array as $stuff){
                      $id = $stuff['id'];
                      $arr = array('id'=>$id,'choice_id'=>'absent');
                      array_push($this->data['evidence'],$arr);
                    }
                    $this->submit();
                    }
                  }else{
                    $this->say("That was an invalid response. Let's try again.");
                    $this->repeat();
                  }
                }
                
              }
                  });
      }
      public function question($items,$question){
        $count = count($items);
        $text = "";
        $index = 0;
        foreach($items as $symptom){
            $key = key($symptom);
            $name = $symptom['name'];
            $i = $index+1;
            $text = $text."\n[".$i."] ".$name;
            $index++;
        }
        return $question."\n".$text;
      }
    public function submit()
    {
      $stuff = $this->check($this->data);
      $json = json_decode($stuff,true);
        if($json['should_stop'] == false){
          $type = $json['question']['type'];
          if($type == "group_multiple"){
            $items = $json['question']['items'];
            $text = $json['question']['text']."\nSend me the numbers of the corresponding statements separated by a comma.\nEg. 2,4,5\nSend none if otherwise";
            $this->groupMultiple($items,$this->question($items,$text));
          }elseif($type == "single"){
            $this->stuff = $json['question']['items'];
            $quiz = $json['question']['text'];
            $id = $json['question']['items'][0]['id'];
            $this->single($quiz,$id);
          }else{
            $this->stuff = $json['question']['items'];
            $choices = [];
            $quiz = $json['question']['text'];
            $items = $json['question']['items'];
            foreach($items as $symptom){
              $id = $symptom['id'];
              $name = $symptom['name'];
              array_push($choices, array("id"=>$id,"name"=>$name));
            }
            $this->groupSingle($quiz,$choices);
          }
        }else{
          $final = json_decode($this->results(),true);
          if($final['label']){
            $this->say("Label:".$final['label']);
          }
          $serious = $final['serious'];
          if(count($serious) == 0){
            $this->say("Results\nSerious: No");
          }else{
            $em = $serious['is_emergency'];
            if($em == true){
              $shit = "Yes";
            }else{
              $shit = "No";
            }
            if($serious['name'] && $serious['common_name']){
              $d = "Is this an emergency? ".$shit."\nName: ".$serious['name']."\nCommon Name: ".$serious['common_name'];
              $this->say($d);
            }
            
          }
          if($final['triage_level']){
            $this->say("Triage Level: ".$final['triage_level']);
          }
          if($final['description']){
            $this->say("description: ".$final['description']);
          }
        }
      
    }

    public function check($data)
    {
        $ch = curl_init('https://api.infermedica.com/covid19/diagnosis');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type:application/json','App-Id: '.$this->appId,'App-Key: '.$this->appKey));
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function results()
    {
      $ch = curl_init('https://api.infermedica.com/v2/triage');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER,array('Content-Type:application/json','App-Id: '.$this->appId,'App-Key: '.$this->appKey));
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->data));
      $response = curl_exec($ch);
      curl_close($ch);
      return $response;
    }
    public function run()
    {
    	$this->appId = "";
    	$this->appKey = "";
      	$this->data = [];
      	$this->evidence = [];
      	$this->data['evidence'] = $this->evidence;
      	$this->items = 0;
      	$this->stuff = [];
      	$this->id = "";
      	$this->diagnose();
        
    }
}

$botman->listen();
?>