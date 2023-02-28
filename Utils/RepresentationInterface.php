<?php

namespace DASHIF;

class RepresentationInterface {
  public $source;
  public $periodNumber;
  public $adaptationNumber;
  public $representationNumber;
  public $payload;

    public function __construct()
    {
      $this->source = null;
      $this->periodNumber = null;
      $this->adaptationNumber = null;
      $this->representationNumber = null;
      $this->payload = null;
    }

    public function getPrintable(){
      return "[$this->source: " .
        "Period $this->periodNumber, " .
        "Adaptation $this->adaptationNumber, " .
        "Representation $this->representationNumber]";
    }

    public function getHandlerType(){
      return null;
    }

    public function getSDType(){
      return null;
    }

    public function getTrackId($boxName, $index){
      return null;
    }

    public function getWidth(){
      return null;
    }
    public function getHeight(){
      return null;
    }

    public function getDefaultKID(){
      return null;
    }

    public function hasBox($boxName){
      return false;
    }

    public function getRawBox($boxName, $index){
      return null;
    }

}

