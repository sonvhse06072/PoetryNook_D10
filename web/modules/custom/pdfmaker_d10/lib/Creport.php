<?php

include 'Cezpdf.php';

// define a clas extension to allow the use of a callback to get the table of contents, and to put the dots in the toc
class Creport extends Cezpdf {

  var $reportContents = array();

  function Creport($p,$o,$t,$op){
    parent::__construct($p,$o,$t,$op);
  }

  function rf($info){
    // this callback records all of the table of contents entries, it also places a destination marker there
    // so that it can be linked too
    $tmp = $info['p'];
    $lvl = $tmp[0];
    $lbl = rawurldecode(substr($tmp,1));
    $num=$this->ezWhatPageNumber($this->ezGetCurrentPageNumber());
    $this->reportContents[] = array($lbl,$num,$lvl );
    $this->addDestination('toc'.(count($this->reportContents)-1),'FitH',$info['y']+$info['height']);
  }

  function dots($info){
    // draw a dotted line over to the right and put on a page number
    $tmp = $info['p'];
    $lvl = $tmp[0];
    $lbl = substr($tmp,1);
    $xpos = 520;

    switch($lvl){
      case '1':
        $size=16;
        $thick=1;
        break;
      case '2':
        $size=12;
        $thick=0.5;
        break;
    }

    $this->saveState();
    $this->setLineStyle($thick,'round','',array(0,10));
    $this->line($xpos,$info['y'],$info['x']+5,$info['y']);
    $this->restoreState();
    $this->addText($xpos+5,$info['y'],$size,$lbl);
  }
}