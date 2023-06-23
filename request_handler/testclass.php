<html>
<body BGCOLOR="BLACK">
<font color="white" size="24">
<div style="width: 60vw; margin: auto; padding-top: 50px;">

<?php 
//Strategy pattern
interface Quackable{
    public function getQuack();
}
    //Different Algorithms/Strategies that implement Quackable()
    //behaviour in class form. 
    //is intended for another object to invoke, call / use or has 
class NormalQuack implements Quackable{
    public function getQuack(){
        return 'quack quack.';
    }
}

class UltiQuack implements Quackable{
    public function getQuack(){
        return 'QUACK QUACK QUACKKK!!!!!!';
    }
}
//

class Duck{
    public $Quack; //the Strategy Object as property of Duck

    function __construct(){
        $this->Quack = new NormalQuack();
    }

    public function quack(){
        //This Duck echo the return value of Strategy object
        echo $this->Quack->getQuack();
    }

    //Accept object that implement Quackable.
    //a.k.a the Algorithm, Strategies / Behaviour 
    public function setQuack(Quackable $Quack){
        $this->Quack = $Quack;
    }
}

//main
$Duck = new Duck();

$Duck->quack();

echo '<hr>';

$Duck->setQuack(new UltiQuack);
$Duck->quack();

