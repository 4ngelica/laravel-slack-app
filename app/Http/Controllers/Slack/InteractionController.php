<?php

namespace App\Http\Controllers\Slack;

use App\Http\Controllers\Controller;
use App\Slack\SlackClient;
use App\Models\Thread;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class InteractionController extends Controller
{
    protected $client;

    public function __construct(SlackClient $client)
    {
        $this->client = $client;
    }

    public function __invoke()
    {
        $data = collect(json_decode(request('payload'), true));

        // Here, handle the interaction based on its type (message_action).
        switch (Arr::get($data, 'type')) {
            case 'message_action':
            $callbackId = Arr::get($data, 'callback_id');

            switch ($callbackId) {
                case 'open_modal':
                $this->handleModal($data);
                break;

                default:
                //
                break;
            }
            break;

            case 'view_submission':
            // The user submitted the modal with some data
            $this->handleViewSubmission($data);
            break;

            default:
            break;
        }

        return response("{\"response_action\" : \"clear\"}", 200)->header('Content-Type', 'application/json');
    }

    public function handleViewSubmission($data)
    {
        //Token
        $authorization = 'Bearer ' . strval(env("SLACK_BOT_USER_TOKEN"));

        //Submitted data
        $selector = array(Arr::get($data, 'view.state.values'));
        $selectedId = [];
        foreach ($selector[0] as $key => $value) {
            array_push($selectedId, $key);
        }
        $viewId = Arr::get($data, 'view.id');
        $teamId = Arr::get($data, 'team.id');
        $message = Arr::get($data, 'view.blocks.4.text.text');

        //Getting data from modal
        $info = Arr::get($data, 'view.blocks.6.text.text');
        $info = explode(': ', $info);
        $ts = $info[2];
        $channel = $info[1];
        $channel = explode(PHP_EOL, $channel);
        $channel = $channel[0];
        $info = $info[0];
        $user = explode('*', $info);
        $user = $user[3];

        //Getting files ids from modal
        $filesIds = Arr::get($data, 'view.blocks.8.text.text');
        $filesIds = explode('ID: ', $filesIds);
        $ids = [];
        $media = '';
        $imageURL = asset('');
        $link = [];
        $ids = [];

        //Getting the author name
        //Users.info method
        $usersInfo = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/users.info', [
            'user' => $user
        ]);

        $userData = collect(json_decode($usersInfo, true));
        $author = Arr::get($userData, 'user.profile.real_name_normalized');

        //Getting the author email
        //Users.info method
        $usersInfo = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/users.info', [
            'user' => $user
        ]);

        $userData = collect(json_decode($usersInfo, true));
        $email = Arr::get($userData, 'user.profile.email');

        //Modal update with success message
        $response = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/views.update', [
            'view_id' => $viewId,
            'view' => '{
                "type": "modal",
                "title": {
                    "type": "plain_text",
                    "text": "Thread Exported"
                },
                "blocks": [
                    {
                        "type": "section",
                        "text": {
                            "type": "plain_text",
                            "text": "The thread was successfully exported."
                        }
                    }
                ]
            }'
        ]);

        if ($filesIds !== NULL) {
            for ($i=1; $i < count($filesIds); $i++) {
                $filesIds[$i] = explode("\n", $filesIds[$i]);
                preg_match_all('/\(<(.+?)>\)/', $filesIds[$i][0], $link);
                $ids = explode(' ', $filesIds[$i][0]);

                //Download files
                $url = $link[1][0];

                $ch = curl_init($url);
                if (!file_exists('images/slack')) {
                  mkdir('images/slack', 0777, true);
                }
                $fp = fopen('images/slack/'.$teamId.'_'.$ids[0].'.png', 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.env('SLACK_USER_TOKEN')]);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);

                $imageURL = asset('images/slack/'.$teamId.'_'. $ids[0] .'.png');
                $media = '<img style="width: 50px; height: 50px; object-fit: cover;" src="'. $imageURL .'"> <br>' . $media;
            }
        }

        $message = $message . '<br><div class="media-container">' . $media . '</div>';
        log::debug($message);

        // Creating thread in database
        $thread = new Thread([
          'author' => $author,
          'message' => $message,
        ]);
        $thread->save();

        //Getting replies
        //Conversations.replies method
        $conversationsReplies = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/conversations.replies', [
            'channel' => $channel,
            'ts' => $ts
        ]);

        $repliesData = collect(json_decode($conversationsReplies, true));
        $repliesCount = intval(Arr::get($repliesData, 'messages.0.reply_count'));
        $replies = [];

        for ($i=0; $i < $repliesCount; $i++) {
            $replies =  array( Arr::get($repliesData, 'messages.'.($i+1).'.text') => Arr::get($repliesData, 'messages.'.($i+1).'.user') ) + $replies;
        }

        $replies = array_reverse($replies);
        $user = Arr::get($data, 'user.id');

        //Chat.postMessage method
        $postMessage = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/chat.postMessage', [
            'channel' => $channel,
            'thread_ts' => $ts,
            'blocks' => '[
                {
        	    "type": "section",
        	    "text": {
        		    "type": "mrkdwn",
        		    "text": ":white_check_mark: This conversation was sent to your aplication!"
        	    }
                },
                {
        	    "type": "divider"
                }
            ]'
        ]);
    }

    public function handleModal($data)
    {
        //Variables
        $triggerId = Arr::get($data, 'trigger_id');
        $authorization = 'Bearer ' . strval(env("SLACK_BOT_USER_TOKEN"));
        $channel = Arr::get($data, 'channel.id');
        $ts = Arr::get($data, 'message.thread_ts');
        $message = Arr::get($data, 'message.text');
        $teamId = Arr::get($data, 'team.id');
        $originalAuthor = Arr::get($data, 'message.user');
        $file = Arr::get($data, 'message.files');
        $files = [];
        $filesBlock = '';

        if($file !== NULL ){
            for ($i=0; $i < count($file); $i++) {
                $files =  array( Arr::get($data, 'message.files.'.($i).'.id') => [Arr::get($data, 'message.files.'.($i).'.url_private_download'), Arr::get($data, 'message.files.'.($i).'.name')]) + $files;
            }
            //Create filesBlock
            foreach ($files as $id => [$link,$name]) {
                $filesBlock = 'ID: ' . $id .' (' . $link . ')\n'. $filesBlock;
            }
        }else{
            $filesBlock = 'No media added.';
        }

        //Conversations.replies method
        $conversationsReplies = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/conversations.replies', [
            'channel' => $channel,
            'ts' => $ts
        ]);

        $repliesData = collect(json_decode($conversationsReplies, true));
        $repliesCount = intval(Arr::get($repliesData, 'messages.0.reply_count'));
        $replies = [];

        for ($i=0; $i < $repliesCount; $i++) {
            $replies =  array( Arr::get($repliesData, 'messages.'.($i+1).'.blocks.0.elements.0.elements.0.text') => Arr::get($repliesData, 'messages.'.($i+1).'.user') ) + $replies;
        }
        $replies = array_reverse($replies);

        if(Arr::get($repliesData, 'error') == 'not_in_channel'){

            //Chat.postEphemeral method
            $postMessage = Http::withHeaders([
                'Authorization' => $authorization,
            ])->asForm()->post('https://slack.com/api/chat.postEphemeral', [
                'channel' => $channel,
                'user' => $originalAuthor,
                'text' => 'olá',
                'blocks' => '[
		            {
			            "type": "section",
			            "text": {
				        "type": "mrkdwn",
				        "text": ":warning: Adding <@threadexport> bot into this channel is required for using the exporter tool.\n"
			             }
		            }
	            ]'
            ]);
        }else{

        //Users.info method
        $usersInfo = Http::withHeaders([
            'Authorization' => $authorization,
        ])->asForm()->post('https://slack.com/api/users.info', [
            'user' => $originalAuthor
        ]);

        $userData = collect(json_decode($usersInfo, true));
        $email = Arr::get($userData, 'user.profile.email');

            //Getting user avatar
            //usersProfile method
            $usersProfile = Http::withHeaders([
                'Authorization' => $authorization,
            ])->asForm()->post('https://slack.com/api/users.profile.get', [
                'user' => $originalAuthor
            ]);

            $userProfile = collect(json_decode($usersProfile, true));
            $avatarHash = Arr::get($userProfile, 'profile.avatar_hash');

            //Views.open method
            $response = Http::withHeaders([
                'Authorization' => $authorization,
                ])->asForm()->post('https://slack.com/api/views.open', [
                    'trigger_id' => $triggerId,
                    'view' => '{
                        "type":"modal",
                        "title":{
                            "type":"plain_text",
                            "text":"Export Thread"
                        },
                        "submit": {
		                    "type": "plain_text",
		                    "text": "Submit",
		                },
                        "blocks":[{
                            "type":"header",
                            "text":{
                                "type":"plain_text",
                                "text":"Welcome to the Thread Export App!",
                                "emoji":true
                            }
                        },
                        {
                            "type":"section",
                            "text":{
                                "type":"plain_text",
                                "text":"Hi! You can export this message into your Laravel application.",
                                "emoji":true
                            }
                        },
                        {
                            "type":"divider"
                        },
                        {
                            "type":"section",
                            "text":{
                                "type":"mrkdwn",
                                "text":"*Body*"
                            }
                        },
                        {
                            "type":"section",
                            "text":{
                                "type":"plain_text",
                                "text":"'.$message.'",
                                "emoji":true
                            }
                        },
                        {
			                "type": "divider"
		                },
		                {
			                "type": "section",
			                "text": {
				                "type": "mrkdwn",
				                "text": "*Author*\n*'.$originalAuthor.'*\nChannel ID: '.$channel.'\nTS: '.$ts.'"
			                },
			                "accessory": {
				                "type": "image",
				                "image_url": "https://ca.slack-edge.com/'.$teamId.'-'.$originalAuthor.'-'.$avatarHash.'-512",
				                "alt_text": "Redwood Suite"
			                }
		                },
                        {
			                "type": "divider"
		                },
                        {
                            "type": "section",
                            "text": {
                                "type": "mrkdwn",
                                "text": "*Media*\n'. $filesBlock .'"
                            }
                        }
                    ]}'
                ]);
        }
      }

    /**
    * Replace all emojis in string.
    *
    * @param string $str
    * @return string
    */
    function emoji_decode($str) {
        $emoji_re = '/\:([a-zA-Z0-9\-_\+]+)\:?/';

        // Find all Slack emoji in the message
        preg_match_all($emoji_re, $str, $matches);

        if (count($matches) < 2) {
            // guard matches
            return;
        }

        // read emojis map file
        $emojis = json_decode(file_get_contents('emojis.json'));

        // loop trough matched short names
        foreach ($matches[1] as $short_name) {
            // find short_name in emojis map
            foreach ($emojis as $emoji) {
                // guard short_name in emoji not set
                if (!isset($emoji->short_name)) return;

                // emoji found
                if ($emoji->short_name == $short_name) {
                    // guard emoji->unified not set
                    if (!isset($emoji->unified)) return;

                    // replace all matches for current emoji in string
                    $str = str_replace(":$short_name:", "&#x" . $emoji->unified, $str);

                }
            }
        }

        return $str;
    }

    /**
    * Parse Slack's markdown to HTML.
    *
    * @param string $str
    * @return string
    */
    public function slack_decode($str)
    {
        // html_entity_decode
        $str = html_entity_decode($str);

        // convert \n
        $str = preg_replace('/\n/', "</br>", $str);

        // convert strong
        $str = preg_replace('/\*(.+?)\*/', "<strong>$1</strong>", $str);

        // convert italic
        $str = preg_replace('/_(.+?)_/', "<i>$1</i>", $str);

        // convert striked
        $str = preg_replace('/~(.+?)~/', "<s>$1</s>", $str);

        // convert link: <https://www.google.com/|google> -> <a href="https://www.google.com/">google</a>
        $str = preg_replace('/<http(.+?)\|(.+?)>/', '<a href="http$1" target="_blank" style="color:#1976d2; text-decoration: underline;">$2</a>', $str);

        // convert block code
        $str = preg_replace('/```(.+?)```/', "<pre><code>$1</code></pre>", $str);

        // convert code
        $str = preg_replace('/`(.+?)`/', "<code>$1</code>", $str);

        // convert emojis
        $str = InteractionController::emoji_decode($str);

        return $str;
    }
}
