<?php

/**
 * HypixelPHP
 *
 * @author Plancke
 * @version 1.2.1
 * @link  http://plancke.nl
 *
 */
class HypixelPHP
{
    private $options;
    const MAX_CACHE_TIME = 999999999999;

    public function  __construct($input = array())
    {
        $this->options = array_merge(
            array(
                'api_key' => '',
                'cache_time' => 600,
                'cache_folder_player' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/player/',
                'cache_folder_guild' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/guild/',
                'cache_folder_friends' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/friends/',
                'cache_folder_sessions' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/sessions/',
                'cache_boosters' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/boosters.json',
                'cache_leaderboards' => $_SERVER['DOCUMENT_ROOT'] . '/cache/HypixelAPI/leaderboards.json',
                'debug' => true,
                'version' => '1.2.1'
            ),
            $input
        );

        if (!file_exists($this->options['cache_folder_player'])) {
            mkdir($this->options['cache_folder_player'], 0777, true);
        }
        if (!file_exists($this->options['cache_folder_guild'])) {
            mkdir($this->options['cache_folder_guild'], 0777, true);
        }
        if (!file_exists($this->options['cache_folder_friends'])) {
            mkdir($this->options['cache_folder_friends'], 0777, true);
        }
        if (!file_exists($this->options['cache_folder_sessions'])) {
            mkdir($this->options['cache_folder_sessions'], 0777, true);
        }
    }

    public function set($input)
    {
        foreach ($input as $key => $val) {
            if ($key != 'api_key') {
                $this->debug('Setting ' . $key . ' to ' . $val);
            }
            $this->options[$key] = $val;
        }
    }

    public function getVersion()
    {
        return $this->options['version'];
    }

    public function debug($message)
    {
        if ($this->options['debug']) {
            echo '<!-- ' . $message . ' -->';
        }
    }

    public function setKey($key)
    {
        $this->set(array('api_key' => $key));
    }

    public function getKey()
    {
        return $this->options['api_key'];
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function hasPaid($name, $url = 'http://www.minecraft.net/haspaid.jsp?user={{NAME}}')
    {
        return @file_get_contents(str_replace('{{NAME}}', $name, $url)) == "true";
    }

    public function getCacheTime()
    {
        return $this->options['cache_time'];
    }

    public function setCacheTime($cache_time = 600)
    {
        $this->set(array('cache_time' => $cache_time));
    }

    public function fetch($request, $key = null, $val = null)
    {
        $requestURL = 'https://api.hypixel.net/' . $request . '?key=' . $this->getKey();
        if ($key != null && $val != null) {
            $requestURL .= '&' . $key . '=' . $val;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestURL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $output = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status != '200') {
            return array("success" => 'false');
        }
        return json_decode($output, true);
    }

    public function getPlayer($keypair = array())
    {
        $pairs = array_merge(
            array(
                'name' => '',
                'uuid' => ''
            ),
            $keypair
        );

        foreach ($pairs as $key => $val) {
            $val = strtolower($val);
            if ($val != null) {
                $filename = $this->options['cache_folder_player'] . $key . '/' . $this->getCacheFileName($val) . '.json';
                if ($key == 'uuid') {
                    $content = json_decode($this->getCache($filename), true);
                    if (is_array($content)) {
                        if (array_key_exists($val, $content)) {
                            if (time() - $this->getCacheTime() < $content[$val]['timestamp']) {
                                $this->debug('Getting Cached data!');
                                return $this->getPlayer(array('name' => $content[$val]['name']));
                            }
                        }
                    }

                    $response = $this->fetch('player', $key, $val);
                    if ($response['success'] == 'true') {
                        if ($response['player'] != null) {
                            $content[$val] = array('timestamp' => time(), 'name' => $response['player']['displayname']);
                            $this->setCache($filename, $content);
                            return new Player($response['player'], $this);
                        }
                    }
                }

                if ($key == 'name') {
                    if (file_exists($filename)) {
                        if (time() - $this->getCacheTime() < filemtime($filename)) {
                            $content = json_decode($this->getCache($filename), true);
                            return new Player($content['record'], $this, true);
                        }
                    }

                    $response = $this->fetch('player', $key, $val);
                    if ($response['success'] == 'true') {
                        if ($response['player'] != null) {
                            $this->setCache($filename, $response['player']);
                            return new Player($response['player'], $this);
                        }
                    }
                }
            }
        }
        if ($this->getCacheTime() < self::MAX_CACHE_TIME) {
            $this->setCacheTime(self::MAX_CACHE_TIME);
            return $this->getPlayer($pairs);
        }
        return new Player(null, $this);
    }

    public function getGuild($keypair = array())
    {
        $pairs = array_merge(
            array(
                'byPlayer' => '',
                'byName' => '',
                'id' => ''
            ),
            $keypair
        );

        foreach ($pairs as $key => $val) {
            if ($val != null) {
                $val = str_replace(' ', '%20', $val);
                $filename = $this->options['cache_folder_guild'] . $key . '/' . $this->getCacheFileName($val) . '.json';

                if ($key == 'byPlayer' || $key == 'byName') {
                    $content = json_decode($this->getCache($filename), true);
                    if ($content != null) {
                        if (is_array($content)) {
                            if (array_key_exists($val, $content)) {
                                if (time() - $this->getCacheTime() < $content[$val]['timestamp']) {
                                    return $this->getGuild(array('id' => $content[$val]['guild']));
                                }
                            }
                        }
                    }

                    $response = $this->fetch('findGuild', $key, $val);
                    if ($response['success'] == 'true') {
                        if ($response['guild'] != null) {
                            $content[$val] = array('timestamp' => time(), 'guild' => $response['guild']);
                            $this->setFileContent($filename, json_encode($content));
                            return $this->getGuild(array('id' => $response['guild']));
                        }
                    }
                }

                if ($key == 'id') {
                    if (file_exists($filename)) {
                        if (time() - $this->getCacheTime() < filemtime($filename)) {
                            $content = json_decode($this->getCache($filename), true);
                            return new Guild($content['record'], $this, true);
                        }
                    }

                    // new/update entry
                    $response = $this->fetch('guild', $key, $val);
                    if ($response['success'] == 'true') {
                        if ($response['guild'] != null) {
                            $this->setCache($filename, $response['guild']);
                            return new Guild($response['guild'], $this);
                        }
                    }
                }

            }
        }
        if ($this->getCacheTime() < self::MAX_CACHE_TIME) {
            $this->setCacheTime(self::MAX_CACHE_TIME);
            return $this->getGuild($pairs);
        }
        return new Guild(null, $this);
    }

    public function getSession($keypair = array())
    {
        $pairs = array_merge(
            array(
                'player' => ''
            ),
            $keypair
        );

        foreach ($pairs as $key => $val) {
            $val = strtolower($val);
            if ($val != null) {
                if ($key == 'player') {
                    $filename = $this->options['cache_folder_sessions'] . $key . '/' . $this->getCacheFileName($val) . '.json';
                    if (file_exists($filename)) {
                        if (time() - $this->getCacheTime() < filemtime($filename)) {
                            $content = json_decode($this->getCache($filename), true);
                            return new Session($content['record'], $this, true);
                        }
                    }

                    $response = $this->fetch('session', $key, $val);
                    if ($response['success'] == 'true') {
                        if ($response['session'] == null) {
                            if ($this->getCacheTime() < self::MAX_CACHE_TIME) {
                                $this->setCacheTime(self::MAX_CACHE_TIME);
                                return $this->getSession($pairs);
                            }
                        }
                        $this->setCache($filename, $response['session']);
                        return new Session($response['session'], $this);
                    }
                }
            }
        }
        return new Session(null, $this);
    }

    public function getFriends($keypair = array())
    {
        $pairs = array_merge(
            array(
                'player' => ''
            ),
            $keypair
        );

        foreach ($pairs as $key => $val) {
            $val = strtolower($val);
            if ($val != null) {
                if ($key == 'player') {
                    $filename = $this->options['cache_folder_friends'] . $key . '/' . $this->getCacheFileName($val) . '.json';
                    if (file_exists($filename)) {
                        if (time() - $this->getCacheTime() < filemtime($filename)) {
                            $content = json_decode($this->getCache($filename), true);
                            return new Friends($content['record'], $this, true);
                        }
                    }

                    $response = $this->fetch('friends', $key, $val);
                    if ($response['success'] == 'true') {
                        if ($response['records'] != null) {
                            $this->setCache($filename, json_encode($response['records']));
                            return new Friends($response['records'], $this);
                        }
                    }
                }
            }
        }
        if ($this->getCacheTime() < self::MAX_CACHE_TIME) {
            $this->setCacheTime(self::MAX_CACHE_TIME);
            return $this->getFriends($pairs);
        }
        return new Friends(null, $this);
    }

    public function getBoosters()
    {
        $filename = $this->options['cache_boosters'];
        if (file_exists($filename)) {
            if (time() - $this->getCacheTime() < filemtime($filename)) {
                $content = $this->getCache($filename);
                $json = json_decode($content, true);
                return new Boosters($json['record'], $this, true);
            }
        }

        $response = $this->fetch('boosters');
        if ($response['success'] == 'true') {
            $this->setCache($filename, $response['boosters']);
            return new Boosters($response['boosters'], $this);
        }
        return new Boosters(null, $this);
    }

    public function getLeaderboards()
    {
        $filename = $this->options['cache_leaderboards'];
        if (file_exists($filename)) {
            if (time() - $this->getCacheTime() < filemtime($filename)) {
                $content = $this->getCache($filename);
                $json = json_decode($content, true);
                return new Leaderboards($json['record'], $this, true);
            }
        }

        $response = $this->fetch('leaderboards');
        if ($response['success'] == 'true') {
            $this->setCache($filename, $response['leaderboards']);
            return new Leaderboards($response['leaderboards'], $this);
        }
        return new Leaderboards(null, $this);
    }

    public function getFileContent($filename)
    {
        $this->debug('Getting contents of ' . $filename);
        if (!file_exists(dirname($filename))) {
            @mkdir(dirname($filename), 0777, true);
        }
        $file = fopen($filename, 'r+');
        $content = null;
        if (file_exists($filename)) {
            if (filesize($filename) > 0) {
                $content = fread($file, filesize($filename));
            }
        }
        fclose($file);
        return $content;
    }

    public function setFileContent($filename, $content)
    {
        $this->debug('Setting contents of ' . $filename);
        if (!file_exists(dirname($filename))) {
            @mkdir(dirname($filename), 0777, true);
        }
        $file = fopen($filename, 'w');
        fwrite($file, $content);
        fclose($file);
    }

    public function getCacheFileName($input)
    {
        $input = strtolower($input);
        $input = str_replace(' ', '', $input);
        if (strlen($input) < 3) {
            return implode('/', str_split($input, 1));
        }
        return substr($input, 0, 1) . '/' . substr($input, 1, 1) . '/' . substr($input, 2);
    }

    public function getCache($filename)
    {
        $content = $this->getFileContent($filename);
        if ($content == null) {
            $content = array();
        }
        return $content;
    }

    public function setCache($filename, $content)
    {
        $write = array();
        $write['timestamp'] = time();
        if (!is_array($content)) {
            $content = json_decode($content, true);
        }
        $write['record'] = $content;

        $this->setFileContent($filename, json_encode($write));
    }

    public function getRanks()
    {
        $ranks = array(
            'ADMIN' => array(
                'prefix' => 'ADMIN',
                'colors' => array(
                    'front' => 'FF5555',
                    'back' => '3F1515'
                )
            ),
            'JR DEV' => array(
                'prefix' => 'JR DEV',
                'colors' => array(
                    'front' => '55FF55',
                    'back' => '153F15'
                )
            ),
            'MODERATOR' => array(
                'prefix' => 'MOD',
                'colors' => array(
                    'front' => '00AA00',
                    'back' => '002A00'
                )
            ),
            'HELPER' => array(
                'prefix' => 'HELPER',
                'colors' => array(
                    'front' => '0000AA',
                    'back' => '00002A'
                )
            ),
            'JR HELPER' => array(
                'prefix' => 'JR HELPER',
                'colors' => array(
                    'front' => '0000AA',
                    'back' => '00002A'
                )
            ),
            'YOUTUBER' => array(
                'prefix' => 'YT',
                'colors' => array(
                    'front' => 'FFAA00',
                    'back' => '2A2A00'
                )
            ),
            'MVP+' => array(
                'prefix' => 'MVP+',
                'colors' => array(
                    'front' => '22CCCC',
                    'back' => '153F3F',
                    'plus' => 'FF5555'
                )
            ),
            'MVP' => array(
                'prefix' => 'MVP',
                'colors' => array(
                    'front' => '22CCCC',
                    'back' => '153F3F'
                )
            ),
            'VIP+' => array(
                'prefix' => 'VIP+',
                'colors' => array(
                    'front' => '22CC22',
                    'back' => '153F15',
                    'plus' => 'FFAA00'
                )
            ),
            'VIP' => array(
                'prefix' => 'VIP',
                'colors' => array(
                    'front' => '22CC22',
                    'back' => '153F15'
                )
            ),
            'DEFAULT' => array(
                'colors' => array(
                    'front' => 'AAAAAA',
                    'back' => 'A2A2A2'
                )
            ),
            'NONE' => array(
                'colors' => array(
                    'front' => 'AAAAAA',
                    'back' => 'A2A2A2'
                )
            )
        );

        return $ranks;
    }

    public function getRankInfo($rank = 'NONE')
    {
        $rankInfo = $this->getRanks();
        if (!array_key_exists($rank, $rankInfo)) {
            $rank = 'NONE';
        }
        return $rankInfo[$rank];
    }
}

class HypixelObject
{
    public $JSONArray;
    public $api;
    public $cached;

    public function __construct($json, HypixelPHP $api, $cached = false)
    {
        $this->JSONArray = $json;
        $this->api = $api;
        $this->cached = $cached;
    }

    public function isNull()
    {
        return $this->getRaw() == null;
    }

    public function getRaw()
    {
        return $this->JSONArray;
    }

    public function get($key, $implicit = false, $default = null)
    {
        if ($this->isNull()) return $default;
        if (!$implicit) {
            $return = $this->JSONArray;
            foreach (explode(".", $key) as $split) {
                $return = isset($return[$split]) ? $return[$split] : $default;
            }
            return $return ? $return : $default;
        }
        return in_array($key, array_keys($this->JSONArray)) ? $this->JSONArray[$key] : $default;
    }

    public function getId()
    {
        return $this->get('_id', true);
    }

    public function isCached()
    {
        return $this->cached;
    }
}

class Player extends HypixelObject
{
    public function getSession()
    {
        return $this->api->getSession(array('player' => $this->getName()));
    }

    public function getFriends()
    {
        return $this->api->getFriends(array('player' => $this->getName()));
    }

    public function getName()
    {
        if ($this->get('displayname', true) != null) {
            return $this->get('displayname', true);
        } else {
            $aliases = $this->get('knownAliases', true, array());
            if (sizeof($aliases) == 0) {
                return $this->get('playername', true);
            }
            return $aliases[0];
        }
    }

    public function getColoredName($rankOptions = array(false, false), $extraCSS = '')
    {
        $playerRank = $this->getRank($rankOptions[0], $rankOptions[1]);
        $rankInfo = $this->api->getRankInfo($playerRank);
        $rankPrefix = $this->getPrefix();
        if ($rankPrefix != null) {
            /*
            if (array_key_exists('prefix', $rankInfo)) {
                return '<span style="color: #' . $rankInfo['colors']['front'] . ';' . $extraCSS . '">' . $prefix . $this->getName() . '</span>';
            }*/
        }
        $prefix = '';
        if (array_key_exists('prefix', $rankInfo)) {
            $prefix = '[' . $rankInfo['prefix'] . '] ';
            if (array_key_exists('plus', $rankInfo['colors'])) {
                $prefix = str_replace('+', '<span style="color: #' . $rankInfo['colors']['plus'] . ';">+</span>', $prefix);
            }
        }
        return '<span style="color: #' . $rankInfo['colors']['front'] . ';' . $extraCSS . '">' . $prefix . $this->getName() . '</span>';
    }

    public function getUUID()
    {
        return $this->get('uuid');
    }

    public function getStats()
    {
        return new Stats($this->get('stats', true, array()), $this->api);
    }

    public function isPreEULA()
    {
        return $this->get('eulaCoins', true, false);
    }

    public function getLevel()
    {
        return $this->get('networkLevel', true, 0) + 1;
    }

    public function getPrefix()
    {
        return $this->get('prefix', true);
    }

    public function isStaff()
    {
        $rank = $this->get('rank', true);
        if ($rank == 'NORMAL' || $rank == null)
            return false;
        return true;
    }

    public function getMultiplier()
    {
        if ($this->getRank(false) == 'YOUTUBER') return 7;
        $ranks = array('DEFAULT', 'VIP', 'VIP+', 'MVP', 'MVP+');
        $pre = $this->getRank(true, true);
        $flip = array_flip($ranks);
        $rankKey = $flip[$pre] + 1;
        $levelKey = floor($this->getLevel() / 25) + 1;
        return ($rankKey > $levelKey) ? $rankKey : $levelKey;
    }

    public function getRank($package = true, $preEULA = false)
    {
        $return = 'DEFAULT';
        if ($package) {
            $keys = array('newPackageRank', 'packageRank');
            if ($preEULA) $keys = array_reverse($keys);
            if (!$this->isStaff()) {
                if (!$this->isPreEULA()) {
                    if ($this->get($keys[0], true)) {
                        $return = $this->get($keys[0], true);
                    }
                } else {
                    foreach ($keys as $key) {
                        if ($this->get($key, true)) {
                            $return = $this->get($key, true);
                            break;
                        }
                    }
                }
            } else {
                foreach ($keys as $key) {
                    if ($this->get($key, true)) {
                        $return = $this->get($key, true);
                        break;
                    }
                }
            }
        } else {
            $rank = $this->get('rank', true);
            if (!$this->isStaff()) return $this->getRank(true, $preEULA);
            $return = $rank;
        }
        if ($return == 'NONE' && $preEULA) {
            return $this->getRank($package, !$preEULA);
        }
        return str_replace('_', ' ', str_replace('_PLUS', '+', $return));
    }

    public function getAchievements()
    {
        return 'D:';
    }

    public function getAchievementPoints()
    {
        return 'WIP';
    }
}

class Achievements extends HypixelObject
{
    const GAME_GENERAL = "general";
    const GAME_QUAKE = "quake";
    const GAME_PAINTBALL = "paintball";
    const GAME_WALLS = "walls";
    const GAME_VAMPIREZ = "vampirez";
    const GAME_BLITZ = "blitz";
    const GAME_MEGAWALLS = "walls3";
    const GAME_ARENA = "arena";

    const GAME_ARCADE = "arcade";
    const GAME_ARCADE_CREEPERATTACK = "arcade.creeper";

    const GAME_TNTGAMES = "tntgames";
    const GAME_TNTGAMES_WIZARDS = "tntgames.wizards";
    const GAME_TNTGAMES_BOW = "tntgames.bow";
    const GAME_TNTGAMES_TNT_RUN = "tntgames.tnt.run";
    const GAME_TNTGAMES_TNT_TAG = "tntgames.tnt.tag";


    private $player;

    public function __construct($json, $api, $player)
    {
        parent::__construct($json, $api);
        $this->player = $player;
    }

    public function getByGame($key/* should be the game name */)
    {
        $achievements = $this->JSONArray;
        $availableGamesList = array();
        foreach ($achievements as $achievement) {
            $explode = explode('_', $achievement);
            $gameName = $explode[0];
            if (!in_array($gameName, array_keys($availableGamesList))) {
                $availableGamesList[$gameName] = array();
            }
            if (in_array($gameName, array("arcade", "tntgames"))) {
                $subGameName = $explode[1];
                if (!in_array($subGameName, array_keys($availableGamesList[$gameName]))) $availableGamesList[$gameName][$subGameName] = array();
                $availableGamesList[$gameName][$subGameName][] = $achievement;
            } else {
                $availableGamesList[$gameName][] = $achievement;
            }
        }
        if (count($availableGamesList) <= 0)
            return null;
        $tmpGameList = $availableGamesList;
        foreach (explode('.', $key) as $subGame) {
            if (in_array($subGame, array_keys($tmpGameList))) $tmpGameList = $tmpGameList[$subGame];
            else return null;
        }
        $ret = array();
        foreach ($tmpGameList as $game => $achievement) {
            $ret[] = new OneTimeAchievement($achievement, $this->player);
        }
        return $ret;
    }

    public function hasAchievement($ach)
    {
        return in_array($ach, $this->JSONArray);
    }
}

class OneTimeAchievement
{

    private $name;
    private $player;

    public function __construct($key = null, $player = null)
    {
        $this->name = $key;
        $this->player = $player;
    }

    public function isNull()
    {
        return $this->name == null || !is_string($this);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        // not done for now
    }

    public function __toString()
    {
        if ($this->isNull()) {
            return "";
        }
        return $this->name;
    }
}

class TieredAchievement
{
}

class Tier
{
}

class Stats extends HypixelObject
{
    public function getGame($game)
    {
        return new GameStats(isset($this->JSONArray[$game]) ? $this->JSONArray[$game] : null, $this->api);
    }
}

class GameStats extends HypixelObject
{ /* Dummy for now */
}

class Session extends HypixelObject
{
    public function getPlayers()
    {
        return $this->get('players', true);
    }

    public function getGame()
    {
        return $this->get('gameType', true);
    }

    public function getServer()
    {
        return $this->get('server', true);
    }
}

class Friends extends HypixelObject
{ /* Dummy for now */
}

class Guild extends HypixelObject
{
    private $members;

    public function getName()
    {
        return $this->get('name', true);
    }

    public function canTag()
    {
        return $this->get('canTag', true);
    }

    public function getTag()
    {
        return $this->get('tag', true);
    }

    public function getCoins()
    {
        return $this->get('coins', true);
    }

    public function getMemberList()
    {
        if ($this->members == null)
            $this->members = new MemberList($this->JSONArray['members'], $this->api);
        return $this->members;
    }

    public function getMaxMembers()
    {
        $total = 25;
        $level = $this->get('memberSizeLevel', true, -1);
        if ($level >= 0) {
            $total += 5 * $level;
        }
        return $total;
    }

    public function getMemberCount()
    {
        return $this->getMemberList()->getMemberCount();
    }
}

class MemberList extends HypixelObject
{
    private $list;
    private $count;

    public function __construct($json, $api)
    {
        $this->JSONArray = $json;
        $this->api = $api;

        $list = array("GUILDMASTER" => array(), "OFFICER" => array(), "MEMBER" => array());
        $this->count = sizeof($json);
        foreach ($json as $player) {
            $rank = $player['rank'];
            if (!in_array($rank, array_keys($list))) {
                $list[$rank] = array();
            }

            array_push($list[$rank], $player);
        }
        $this->list = $list;
    }

    public function getList()
    {
        return $this->list;
    }

    public function getMemberCount()
    {
        return $this->count;
    }
}

class Boosters extends HypixelObject
{ /* Dummy for now */
}

class Leaderboards extends HypixelObject
{ /* Dummy for now */
}