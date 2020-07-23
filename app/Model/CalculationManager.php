<?php

namespace App\Model;

use Nette;
use Nette\Database\Context;
class CalculationManager
{
    use Nette\SmartObject;

    private $database;
    private $table = 'calculations';

    public function __construct(Context $database){
        $this->database = $database;
    }

    public function find(int $id){
        return $this->database->table($this->table)->get($id);
    }

    public function create(array $values){
        return $this->database->table($this->table)->insert($values);
    }

    public function products(int $id){
        return $this->database->table($this->table)->get($id)->related('products','calculation_id');
    }

    public function createProduct($values){
        $this->database->table('products')->insert($values);
    }

}