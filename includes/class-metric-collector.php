<?php

/* Based off php statsd client by Dominik Liebler <liebler.dominik@googlemail.com>
 * https://github.com/domnikl/statsd-php
 */

if (!defined('SAMPLE_RATE')) define( 'SAMPLE_RATE', 0.5 );

define('SAVEQUERIES', true);
define( 'SKIP_URL_METRICS', false );

class MetricsCollector {

    private $collector;

    public function __construct() {
        //create global
        global $collector;
        $collector_connection = new Connection("carbon.hostedgraphite.com", 2003);
        $collector = new Collector($collector_connection, '');
        $this->collector = &$collector;

        //action hooks
        add_action( 'wp_login', array(&$this, 'login') );
        add_action( 'wp_logout', array(&$this, 'logout') );
        add_action( 'wp_login_failed', array(&$this, 'login_fail') );
        add_action( 'retrieve_password_key', array(&$this, 'password_reset_gen') );
        add_action( 'password_reset', array(&$this, 'password_reset_complete') );
        add_action( 'user_register', array(&$this, 'user_register') );

        add_action( 'publish_post', array(&$this, 'publish_post') );
        add_action( 'wp_trash_post', array(&$this, 'trash_post') );
        add_action( 'delete_post', array(&$this, 'delete_post') );

        add_action( 'wp_insert_comment', array(&$this, 'new_comment') );
        add_action( 'wp_set_comment_status', array(&$this, 'approve_comment'), 10, 2 );
        add_action( 'trash_comment', array(&$this, 'trash_comment') );
        add_action( 'spam_comment', array(&$this, 'spam_comment') );
        add_action( 'unspam_comment', array(&$this, 'unspam_comment') );

        add_action( 'add_attachment', array(&$this, 'add_attachment') );
        add_action( 'edit_attachment', array(&$this, 'edit_attachment') );
        add_action( 'delete_attachment', array(&$this, 'delete_attachment') );

        //multisite only hooks
        if (is_multisite()) {
            add_action( 'wpmu_new_user', array(&$this, 'user_register') );
            add_action( 'wpmu_new_blog', array(&$this, 'new_blog') );
            add_action( 'make_spam_blog', array(&$this, 'spam_blog') );
            add_action( 'make_ham_blog', array(&$this, 'ham_blog') );
            add_action( 'make_spam_user', array(&$this, 'spam_user') );
            add_action( 'make_ham_user', array(&$this, 'ham_user') );
            add_action( 'archive_blog', array(&$this, 'archive_blog') );
            add_action( 'unarchive_blog', array(&$this, 'unarchive_blog') );
            add_action( 'make_delete_blog', array(&$this, 'delete_blog') );
            add_action( 'make_undelete_blog', array(&$this, 'undelete_blog') );
            add_action( 'init', array(&$this, 'blog_count') );
        }

        add_action( 'init', array(&$this, 'user_count') ); //multisite aware

        //http request timing
        add_filter( 'pre_http_request', array(&$this, 'pre_http'), 10, 3 );
        add_action( 'http_api_debug', array(&$this, 'post_http'), 10, 5 );

        //wpdb
        add_action( 'shutdown', array(&$this, 'num_queries') );

        add_action( 'shutdown', array(&$this, 'load_time') );

        add_action( 'xmlrpc_call', array(&$this, 'xmlrpc_call') );

        //trac wp cron
        add_action( 'init', array(&$this, 'wp_cron') );

        //wp_mail
        add_filter( 'wp_mail', array(&$this, 'wp_mail') );
    }

    /* logins/registration */
    public function login($username) {
        $this->collector->increment("wordpress.logins.login");
    }

    public function logout() {
        $this->collector->increment("wordpress.logins.logout");
    }

    public function login_fail($username) {
        $this->collector->increment("wordpress.logins.fail");
    }

    public function password_reset_gen($username) {
        $this->collector->increment("wordpress.logins.reset_start");
    }

    public function password_reset_complete($user) {
        $this->collector->increment("wordpress.logins.reset_complete");
    }

    public function user_register($user_id) {
        $this->collector->increment("wordpress.users.register");
    }

    /* normal blog actions */
    public function publish_post($id) {
        $this->collector->increment("wordpress.posts.publish");
    }

    public function trash_post($id) {
        $this->collector->increment("wordpress.posts.trash");
    }

    public function delete_post($id) {
        $this->collector->increment("wordpress.posts.delete");
    }

    public function new_comment($id) {
        $this->collector->increment("wordpress.comments.new");
    }

    public function approve_comment($id, $status) {
        if ('approve' == $status)
            $this->collector->increment("wordpress.comments.approve");
    }

    public function spam_comment($id) {
        $this->collector->increment("wordpress.comments.spam");
    }

    public function unspam_comment($id) {
        $this->collector->increment("wordpress.comments.unspam");
    }

    public function trash_comment($id) {
        $this->collector->increment("wordpress.comments.trash");
    }

    public function add_attachment($id) {
        $this->collector->increment("wordpress.attachments.add");
    }

    public function edit_attachment($id) {
        $this->collector->increment("wordpress.attachments.edit");
    }

    public function delete_attachment($id) {
        $this->collector->increment("wordpress.attachments.delete");
    }

    /* multisite */
    public function new_blog($id) {
        $this->collector->increment("wordpress.blogs.new");
    }

    public function spam_blog($id) {
        $this->collector->increment("wordpress.blogs.spam");
    }

    public function ham_blog($id) {
        $this->collector->increment("wordpress.blogs.unspam");
    }

    public function spam_user($id) {
        $this->collector->increment("wordpress.users.spam");
    }

    public function ham_user($id) {
        $this->collector->increment("wordpress.users.unspam");
    }

    public function delete_blog($id) {
        $this->collector->increment("wordpress.blogs.delete");
    }

    public function undelete_blog($id) {
        $this->collector->increment("wordpress.blogs.undelete");
    }

    public function archive_blog($id) {
        $this->collector->increment("wordpress.blogs.archive");
    }

    public function unarchive_blog($id) {
        $this->collector->increment("wordpress.blogs.unarchive");
    }

    public function blog_count() {
        //Only send this gauge on every hundredth request, it doesn't change often
        $sample = mt_rand() / mt_getrandmax();
        if ($sample <= 0.01) {
            $this->collector->gauge("wordpress.blogs.count", get_blog_count());
        }
    }

    public function user_count() {
        //Only send this gauge on every hundredth request, it doesn't change often
        $sample = mt_rand() / mt_getrandmax();
        if ($sample <= 0.01) {
            if (is_multisite()) {
                $user_count = get_user_count();
            } else {
                //$user_count = count_users();
                //$user_count = $user_count['total_users'];
                global $wpdb;
                $user_count = $wpdb->get_var( "SELECT COUNT(ID) as c FROM $wpdb->users" ); //don't go by role, make it simple
            }
            $this->collector->gauge("wordpress.users.count", $user_count);
        }
    }

    public function pre_http($false, $r, $url) {
        if ( ! is_multisite() || (defined( 'SKIP_URL_METRICS' ) && SKIP_URL_METRICS == false) ) {
            if ( false !== strpos( parse_url($url, PHP_URL_PATH), 'wp-cron.php' ) ) {
                $url = 'wp_cron';
            } else {
                $url = preg_replace('/[^A-Za-z0-9-]/', '_', parse_url($url, PHP_URL_HOST)); //replace other characters with underscores for graphite
            }
        } else {
            $url = 'all'; //in multisite the unique http urls can be too high and clog statsd
        }
        $this->collector->startTiming("wordpress.http.requests.$url");
        $this->collector->startMemoryProfile("wordpress.http.memory_usage");
        return $false;
    }

    public function post_http($response, $type, $class, $args, $url) {
        if ( ! is_multisite() || defined( 'SKIP_URL_METRICS' ) ) {
            if ( false !== strpos( parse_url($url, PHP_URL_PATH), 'wp-cron.php' ) ) {
                $url = 'wp_cron';
            } else {
                $url = preg_replace('/[^A-Za-z0-9-]/', '_', parse_url($url, PHP_URL_HOST)); //replace other characters with underscores for graphite
            }
        } else {
            $url = 'all'; //in multisite the unique http urls can be too high and clog statsd
        }
        $this->collector->startBatch();
        $this->collector->endTiming("wordpress.http.requests.$url");
        $this->collector->increment("wordpress.http.counts.$url");
        $this->collector->endMemoryProfile("wordpress.http.memory_usage");
        $this->collector->endBatch();
    }

    public function num_queries() {
        //if query tracking is on get specific query details
        if ( defined( 'SAVEQUERIES' ) ) {
            global $wpdb;
            if ( is_array( $wpdb->queries ) && count( $wpdb->queries ) ) {
                //generate rollups by query type
                $counts = $times = array();
                foreach ( $wpdb->queries as $query ) {
                    $type = strtolower( strtok( $query[0], " " ) ); //get query type (insert, delete, update, etc)
                    $time = $query[1];
                    $times[$type] = isset( $times[$type] ) ? $times[$type] + $time : $time;
                    $counts[$type] = isset( $counts[$type] ) ? $counts[$type] + 1 : 1;
                }

                //now loop through types and send agregate data for each
                $this->collector->startBatch();
                foreach ( $counts as $type => $count ) {
                    $this->collector->timing("wordpress.wpdb.queries.$type", round( $times[$type] * 1000 ) );
                    $this->collector->count("wordpress.wpdb.queries.$type", $count);
                }
                $this->collector->endBatch(); //send batched stats
            }

        } else { //SAVEQUERIES off

            $this->collector->count("wordpress.wpdb.queries.all", get_num_queries(), SAMPLE_RATE);

        }
    }

    public function load_time() {
        $load_time = round( 1000 * timer_stop(0) );
        $this->collector->timing("wordpress.load_time", $load_time, SAMPLE_RATE);
    }

    public function xmlrpc_call($type) {
        //track the actual call types here
        $this->collector->increment("wordpress.xmlrpc.$type");
    }

    public function wp_cron() {
        if ( defined('DOING_CRON') ) {
            $this->collector->increment("wordpress.cron");
        }
    }

    public function wp_mail($wp_mail) {
        $this->collector->increment("wordpress.email");
        return $wp_mail;
    }
}

class Collector
{
    /**
     * Connection object that messages get send to
     *
     * @var Connection
     */
    protected $_connection;

    /**
     * holds all the timings that have not yet been completed
     *
     * @var array
     */
    protected $_timings = array();

    /**
     * holds all memory profiles like timings
     *
     * @var array
     */
    protected $_memoryProfiles = array();

    /**
     * global key namespace
     *
     * @var string
     */
    protected $_namespace = '';

    /**
     * stores the batch after batch processing was started
     *
     * @var array
     */
    protected $_batch = array();

    /**
     * batch mode?
     *
     * @var boolean
     */
    protected $_isBatch = false;

    /**
     * inits the Client object
     *
     * @param Connection $connection
     * @param string $namespace global key namespace
     */
    public function __construct($connection, $namespace = '')
    {
        $this->_connection = $connection;
        $this->_namespace = (string) $namespace;
    }

    /**
     * increments the key by 1
     *
     * @param string $key
     * @param int $sampleRate
     *
     * @return void
     */
    public function increment($key, $sampleRate = 1)
    {
        $this->count($key, 1, $sampleRate);
    }

    /**
     * decrements the key by 1
     *
     * @param string $key
     * @param int $sampleRate
     *
     * @return void
     */
    public function decrement($key, $sampleRate = 1)
    {
        $this->count($key, -1, $sampleRate);
    }
    /**
     * sends a count to statsd
     *
     * @param string $key
     * @param int $value
     * @param int $sampleRate (optional) the default is 1
     *
     * @return void
     */
    public function count($key, $value, $sampleRate = 1)
    {
        $this->_send($key, (int) $value, 'c', $sampleRate);
    }

    /**
     * sends a timing to statsd (in ms)
     *
     * @param string $key
     * @param int $value the timing in ms
     * @param int $sampleRate the sample rate, if < 1, statsd will send an average timing
     *
     * @return void
     */
    public function timing($key, $value, $sampleRate = 1)
    {
        $this->_send($key, (int) $value, 'ms', $sampleRate);
    }

    /**
     * starts the timing for a key
     *
     * @param string $key
     *
     * @return void
     */
    public function startTiming($key)
    {
        $this->_timings[$key] = gettimeofday(true);
    }

    /**
     * ends the timing for a key and sends it to statsd
     *
     * @param string $key
     * @param int $sampleRate (optional)
     *
     * @return void
     */
    public function endTiming($key, $sampleRate = 1)
    {
        $end = gettimeofday(true);

        if (array_key_exists($key, $this->_timings)) {
            $timing = ($end - $this->_timings[$key]) * 1000;
            $this->timing($key, $timing, $sampleRate);
            unset($this->_timings[$key]);
        }
    }

    /**
     * start memory "profiling"
     *
     * @param string $key
     *
     * @return void
     */
    public function startMemoryProfile($key)
    {
        $this->_memoryProfiles[$key] = memory_get_usage();
    }

    /**
     * ends the memory profiling and sends the value to the server
     *
     * @param string $key
     * @param int $sampleRate
     *
     * @return void
     */
    public function endMemoryProfile($key, $sampleRate = 1)
    {
        $end = memory_get_usage();

        if (array_key_exists($key, $this->_memoryProfiles)) {
            $memory = ($end - $this->_memoryProfiles[$key]);
            $this->memory($key, $memory, $sampleRate);

            unset($this->_memoryProfiles[$key]);
        }
    }

    /**
     * report memory usage to statsd. if memory was not given report peak usage
     *
     * @param string $key
     * @param int $memory
     * @param int $sampleRate
     *
     * @return void
     */
    public function memory($key, $memory = null, $sampleRate = 1)
    {
        if (null === $memory) {
            $memory = memory_get_peak_usage();
        }

        $this->count($key, (int) $memory, $sampleRate);
    }

    /**
     * executes a Closure and records it's execution time and sends it to statsd
     * returns the value the Closure returned
     *
     * @param string $key
     * @param \Closure $_block
     * @param int $sampleRate (optional) default = 1
     *
     * @return mixed
     */
    public function time($key, Closure $_block, $sampleRate = 1)
    {
        $this->startTiming($key);
        $return = $_block();
        $this->endTiming($key, $sampleRate);

        return $return;
    }

    /**
     * sends a gauge, an arbitrary value to StatsD
     *
     * @param string $key
     * @param int $value
     *
     * @return void
     */
    public function gauge($key, $value)
    {
        $this->_send($key, (int) $value, 'g', 1);
    }

    /**
     * actually sends a message to to the daemon and returns the sent message
     *
     * @param string $key
     * @param int $value
     * @param string $type
     * @param int $sampleRate
     *
     * @return void
     */
    protected function _send($key, $value, $type, $sampleRate)
    {
            if (0 != strlen($this->_namespace)) {
                    $key = sprintf('%s.%s', $this->_namespace, $key);
            }

            $message = sprintf("%s %d", $key, $value);

            if ( empty( $message ) ) return false; //skip sending empty data

            if (!$this->_isBatch) {
                $this->_connection->send($message);
            } else {
                $this->_batch[] = $message;
            }
    }

    /**
     * changes the global key namespace
     *
     * @param string $namespace
     *
     * @return void
     */
    public function setNamespace($namespace)
    {
        $this->_namespace = (string) $namespace;
    }

    /**
     * gets the global key namespace
     *
     * @return string
     */
    public function getNamespace()
    {
        return $this->_namespace;
    }

    /**
     * is batch processing running?
     *
     * @return boolean
     */
    public function isBatch()
    {
        return $this->_isBatch;
    }

    /**
     * start batch-send-recording
     *
     * @return void
     */
    public function startBatch()
    {
        $this->_isBatch = true;
    }

    /**
     * ends batch-send-recording and sends the recorded messages to the connection
     *
     * @return void
     */
    public function endBatch()
    {
        $this->_isBatch = false;
        $this->_connection->send(join("\n", $this->_batch));
        $this->_batch = array();
    }

    /**
     * stops batch-recording and resets the batch
     *
     * @return void
     */
    public function cancelBatch()
    {
        $this->_isBatch = false;
        $this->_batch = array();
    }
}


class Connection
{
    /**
     * host name
     *
     * @var string
     */
    protected $_host;

    /**
     * port number
     *
     * @var int
     */
    protected $_port;

    /**
     * the used socket resource
     *
     * @var resource
     */
    protected $_socket;

    /**
     * is sampling allowed?
     *
     * @var bool
     */
    protected $_forceSampling = false;

    /**
     * instantiates the Connection object and a real connection to statsd
     *
     * @param string $host
     * @param int $port
     */
    public function __construct($host = 'localhost', $port = 2003)
    {
        $this->_host = (string) $host;
        $this->_port = (int) $port;
        $this->options = array(
                'auth' => array(get_option('hg_stats_api_key', ''), '')
            );
    }

    /**
     * sends a message to the UDP socket
     *
     * @param $message
     *
     * @return void
     */
    public function send($message)
    {
        $message = $message;
        if (0 != strlen($message)) {
            try {
                Requests::post('http://www.hostedgraphite.com/api/v1/sink', [], $message, $this->options);
            } catch (Exception $e) {}
        }
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->_host;
    }


    /**
     * @return int
     */
    public function getPort()
    {
        return $this->_port;
    }

    /**
     * is sampling forced?
     *
     * @return boolean
     */
    public function forceSampling()
    {
        return (bool) $this->_forceSampling;
    }
}
