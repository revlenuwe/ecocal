<?php


namespace App\Model;
use Nette;
use Nette\Database\Context;

class ProductManager
{

    use Nette\SmartObject;


    private $database;
    private $table = 'products';

    public function __construct(Context $database){
        $this->database = $database;
    }

    public function find(int $id){
        return $this->database->table($this->table)->get($id);
    }

    public function create(array $values){
        return $this->database->table($this->table)->insert($values);
    }
}