<?php
use Classes\AdminCommand;
use Classes\Argument;
use Classes\Forum;
use Classes\Json;

class ForumCmd extends AdminCommand
{
    public function __construct() {
        parent::__construct("forum",[new Argument('action',false),new Argument('startDate',true),new Argument('maxTopic',true)]);
        parent::setDescription(<<<EOT
Gere l'indexation des forums pour le moteur de recherche interne.
Exemple:
> forum clearIndex
> forum buildIndex
> forum buildIndex 12721584121 10 //start from topic date 12721584121 ( excluded ), limit 10 posts
EOT);
    }

    public function execute(  array $argumentValues ) : string
    {

        $action = $argumentValues[0];

        if (!in_array($action, ['clearIndex', 'buildIndex'])) {
            $this->result->Error("Action invalid: {$action} use clearIndex or buildIndex");
        }

        if ($action === 'clearIndex') {
            $this->db->executeQuery("DELETE FROM `forums_keywords`");
            $this->result->Log("Forum search index cleared.");
        }
        if ($action === 'buildIndex') {

            if (!empty($argumentValues[1]) && is_numeric($argumentValues[1])) {
                $startDate = (int)$argumentValues[1];
            } else {
                $startDate = 0;
            }
            if (!empty($argumentValues[2]) && is_numeric($argumentValues[2])) {
                $maxTopic = (int)$argumentValues[2];
            } else {
                $maxTopic = 10000;
            }
            $topicsToProcess = [];
            foreach (array('RP', 'Privés', 'HRP') as $cat) {


                $catJson = json()->decode('forum', 'categories/' . $cat);


                foreach ($catJson->forums as $forum) {
                    $forJson = json()->decode('forum', 'forums/' . $forum->name);
                    foreach ($forJson->topics as $topics) {

                        if ($topics->name <= $startDate) {
                            continue;
                        }
                        $topicsToProcess[] = $topics->name;
                    }
                }
            }
            $processedTopics = 0;
            sort($topicsToProcess, SORT_NUMERIC);
            $lastTopicDate = 0;
            foreach ($topicsToProcess as $topicName) {
                $topJson = json()->decode('forum/topics', $topicName);
                if(!$topJson){
                    $this->result->Warning("Topic not found: {$topicName}");
                    continue;
                }
                foreach ($topJson->posts as $post) {

                    $postJson = json()->decode('forum/posts', $post->name);
                    if ($postJson !== false && !empty($postJson->text)) {
                        Forum::put_keywords($post->name, $postJson->text);
                    }
                }
                $lastTopicDate = $topicName;
                $processedTopics++;
                if ($processedTopics >= $maxTopic) {
                    break;
                }
            }

            $this->result->Log("Forum search index built. for {$processedTopics} topics. last={$lastTopicDate}");
        }
        return '';
    }
}
