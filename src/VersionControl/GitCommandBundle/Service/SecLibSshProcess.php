<?php

/*
 * This file is part of the GitCommandBundle package.
 *
 * (c) Paul Schweppe <paulschweppe@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace VersionControl\GitCommandBundle\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use VersionControl\GitCommandBundle\Service\SshProcessInterface;

use phpseclib\Net\SSH2;

/**
 * Use PhpSecLib to make SSH2 requests
 * 
 * @link https://github.com/phpseclib/phpseclib
 * @author Paul Schweppe <paulschweppe@gmail.com>
 */
class SecLibSshProcess implements SshProcessInterface 
{


    /**
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var resource
     */
    protected $shell;

    /**
     * @var array
     */
    protected $stdout;

    /**
     * @var array
     */
    protected $stderr;

    /**
     * //EventDispatcherInterface $eventDispatcher,
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
     * @param array $config
     */
    public function __construct()
    {
        $this->shell      = null;
        $this->stdout     = array();
        $this->stdin      = array();
        $this->stderr     = array();
    }

    /**
     * @param string $glue
     * @return array|string
     */
    public function getStdout($glue = "\n")
    {
        if (!$glue) {
            $output = $this->stdout;
        }else{
           $output = implode($glue, $this->stdout); 
        }

        return $output;
    }

    /**
     * @param string $glue
     * @return array|string
     */
    public function getStderr($glue = "\n")
    {
        if (!$glue) {
            return $this->stderr;
        }

        return implode($glue, $this->stderr);
    }

    /**
     * 
     * @param array $commands
     * @param string $host
     * @param string $username
     * @param integer $port
     * @param string $password
     * @param string $pubkeyFile
     * @param string $privkeyFile
     * @param string $passphrase
     * @return type
     */
    public function run(array $commands,$host,$username,$port=22,$password=null,$pubkeyFile=null,$privkeyFile=null,$passphrase=NULL)
    {
        $this->reset();
        
        if($this->shell === NULL){
            $this->connect($host,$username,$port,$password,$pubkeyFile,$privkeyFile,$passphrase);
        }
        
        foreach ($commands as $command) {
            $this->execute($command);
        }

       //$this->disconnect();

        return $this->stdout;
    }
    
    /**
     * Resets out puts for next command
     */
    protected function reset(){
        $this->stdout     = array();
        $this->stdin      = array();
        $this->stderr     = array();
    }

    /**
     * @throws \InvalidArgumentException|\RuntimeException
     * @param array $connection
     * @return void
     */
    protected function connect($host,$username,$port=22,$password=null,$pubkeyFile=null,$privkeyFile=null,$passphrase=NULL)
    {
        $this->shell = new SSH2($host,$port);

        if (!$this->shell) {
            throw new \InvalidArgumentException(sprintf('SSH connection failed on "%s:%s"', $host, $port));
        }

        if (isset($username) && $pubkeyFile != null && $privkeyFile != null) {
            if (!ssh2_auth_pubkey_file($username, $pubkeyFile, $privkeyFile, $passphrase)) {
                throw new \InvalidArgumentException(sprintf('SSH authentication failed for user "%s" with public key "%s"', $username, $pubkeyFile));
            }
        } else if ($username && $password) {
            if (!$this->shell->login($username, $password)) {
                throw new \InvalidArgumentException(sprintf('SSH authentication failed for user "%s"', $username));
            }
        }
        $this->shell->getServerPublicHostKey();      

        $this->stdout = array();
        $this->stdin = array();
    }

    /**
     * @return void
     */
    public function disconnect()
    {
        if($this->shell){
            //$this->shell->disconnect();
        }
    }

    /**
     * @param array $command
     * @return void
     */
    protected function execute($command)
    {

        $this->shell->enableQuietMode(); 
        //print_r($command);
        $stdOutput = $this->shell->exec($command);
        $stdError = $this->shell->getStdError();
        $exitStatus = $this->shell->getExitStatus();
        
        //print_r($stdOutput);
        //print_r($stdError);
       // print_r($exitStatus);
        
        //$exitStatus = $this->shell->getExitStatus();

        $stdout = explode("\n", $stdOutput);
        $stderr = explode("\n", $stdError);


        if (count($stderr) > 1) {
            //print_r($stderr);
             throw new \RuntimeException(sprintf("Error in command shell:%s \n Error Response:%s",$command,implode("\n", $stderr)));
        }

        $this->stdout = array_merge($this->stdout, $stdout);

        if (is_array($stderr)) {
            $this->stderr = array_merge($this->stderr, $stderr);
        }
    }
    
    public function __destruct() {
        $this->disconnect();
    }

}


