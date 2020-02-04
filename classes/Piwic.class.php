<?php

class Piwic {
    protected $piwicUrl;
    protected $piwicToken;
    public $debug;
    
    function __construct(
        string $piwicUrl, 
        string $piwicToken, 
        bool $debug) 
    {
		$this->piwicUrl = $piwicUrl;
		$this->piwicToken = $piwicToken;
        $this->debug = $debug;
	}
}

class PiwicRequest extends Piwic {
    private $method;
    private $args;
    private $format;
    public $debug;
    
    function __construct(
        string $method, 
        string $args,
        string $format,
        bool $debug) 
    {
		$this->piwicMethod = $method;
		$this->piwicArgs = $args;
        $this->piwicFormat = $format;
        $this->debug = $debug;
	}
    
    private function buildPiwicQuery()
    {
        
    }
} 