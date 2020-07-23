<?php
declare(strict_types=1);

namespace App\Presenters;

use App\Model\CalculationManager;
use App\Model\ProductManager;
use Nette\Application\UI\Presenter;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Container;

final class CalculationPresenter extends Presenter
{
    private $calculation;
    private $product;

    public function __construct(CalculationManager $calculationManager,ProductManager $productManager){
        $this->calculation = $calculationManager;
        $this->product = $productManager;

        Container::extensionMethod('integerMask',function (Form $form,string $name,$label = null){
            return $form->addText($name)
                ->addFilter(function ($value){
                    return str_replace(',','',$value);
                })
                ->addRule(Form::FLOAT,'Value must be a number')
                ->addRule(Form::MIN,'Minimum value - 1',1);
        });
    }

    public function renderDecision(int $id){
        
        $calculation = $this->calculation->find($id);

        if(!$calculation){
            $this->error('Error');
        }

        $this->template->calculation = $calculation;
        $this->template->products = $this->calculation->products($id);
    }


    public function createComponentCalculationForm(){
        $form = new Form();

        $form->integerMask('material')->setRequired();
        $form->integerMask('salary')->setRequired();
        $form->integerMask('mo')->setRequired();
        $form->integerMask('so')->setNullable();
        $form->integerMask('ao')->setNullable();
        $form->integerMask('products_count')->setRequired()->setDefaultValue(1);
        $form->addText('profit')
            ->setRequired()
            ->setDefaultValue(0)
            ->addFilter(function ($value){
                return str_replace(' %','',$value);
            })
            ->addRule(Form::INTEGER,'Value must be a number')
            ->addRule(Form::MIN,'Minimum value - 0',0)
            ->addRule(Form::MAX,'Maximum value - 100',100);
        $form->addSubmit('submit');
        $form->onSuccess[] = [$this,'calculationFormSucceeded'];

        return $form;
    }

    public function createComponentWaybillCalculationForm(){
        $form = new Form();
        $select = [
            'material' => 'Material',
            'salary' => 'Salary',
        ];

        $form->integerMask('mo')->setRequired();
        $form->addSelect('mo_base',null, $select);

        $form->integerMask('so')->setNullable();
        $form->addSelect('so_base',null, $select);
        $form->integerMask('ao')->setNullable();
        $form->addSelect('ao_base',null, $select);


        $form->addSubmit('submit');
        $form->onSuccess[] = [$this,'calculationWaybillFormSucceeded'];

        return $form;
    }

    public function calculationFormSucceeded(Form $form,\stdClass $values){
        $calculation = $this->calculation->create([
            'mo' => $values->mo,
            'ao' => $values->ao,
            'so' => $values->so,
        ]);

        $this->calculation->createProduct([
            'calculation_id' => $calculation->id,
            'material' => $values->material,
            'straight_material' => $values->material / $values->products_count,
            'salary' => $values->salary,
            'straight_salary' => $values->salary / $values->products_count,
            'count' => $values->products_count,
            'straight_mo' => $values->mo / $values->products_count,
            'straight_ao' => $values->ao / $values->products_count,
            'straight_so' => $values->so / $values->products_count,
        ]);


        $this->redirect('Calculation:decision',['id' => $calculation->id]);
    }

    public function calculationWaybillFormSucceeded(Form $form,\stdClass $values){
        $calculation = $this->calculation->create([
            'mo' => $values->mo,
            'mo_schedule' => $values->mo_base,
            'ao' => $values->ao,
            'ao_schedule' => $values->ao_base,
            'so' => $values->so,
            'so_schedule' => $values->so_base,
        ]);
        $material = $form->getHttpData(FORM::DATA_TEXT,'material[]');
        $salary = $form->getHttpData(FORM::DATA_TEXT,'salary[]');
        $count = $form->getHttpData(FORM::DATA_TEXT,'products_count[]');

        $this->createProducts($calculation,$material,$salary,$count);
        $this->calculate($calculation);
    }

    private function createProducts(ActiveRow $calculation,$material,$salary,$count){
        for ($i = 0;$i < count($material);$i++){
            $this->product->create([
                'calculation_id' => $calculation->id,
                'material' => $this->filterSymbol($material[$i]),
                'straight_material' => $this->filterSymbol($material[$i]) / $count[$i],
                'salary' => $this->filterSymbol($salary[$i]),
                'straight_salary' => $this->filterSymbol($salary[$i]) / $count[$i],
                'count' => $count[$i],
            ]);
        }
    }

    private function calculate(ActiveRow $calculation){
        $calculation->update([
            'mo_percent' => $this->calculatePercent($calculation->mo,$this->calculation->products($calculation->id)->sum($calculation->mo_schedule)),
            'ao_percent' => $calculation->ao ? $this->calculatePercent($calculation->ao,$this->calculation->products($calculation->id)->sum($calculation->ao_schedule)) : null,
            'so_percent' => $calculation->so ? $this->calculatePercent($calculation->so,$this->calculation->products($calculation->id)->sum($calculation->so_schedule)) : null,
        ]);


        $this->updatePercents($calculation);

        $this->redirect('Calculation:decision',['id' => $calculation->id]);
    }

    private function calculateStraightSchedule($calculation,$product,string $schedule){
        $percent = $schedule.'_percent';
        $overheadSchedule = $schedule.'_schedule';

        $schedule_base = 'straight_'.$calculation->$overheadSchedule;
        return $calculation->$percent * $product->$schedule_base / 100;
    }

    private function updatePercents($calculation){
        foreach ($this->calculation->products($calculation->id) as $it){
            $product = $this->product->find($it->id);
            $product->update([
                'straight_mo' => $this->calculateStraightSchedule($calculation,$product,'mo'),
                'straight_ao' => $this->calculateStraightSchedule($calculation,$product,'ao'),
                'straight_so' => $this->calculateStraightSchedule($calculation,$product,'so'),
            ]);
        }
    }

    private function calculatePercent($schedule,$sum){
        return round($schedule / $sum * 100,2);
    }

    private function calculateByQuantity($sum,$quantity){
        return round($sum / $quantity,2);
    }

    private function filterSymbol(string $value){
        return str_replace(',','',$value);
    }
}