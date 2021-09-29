<?php
namespace FacturaScripts\Plugins\SaldosPartidas;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Ejercicio;
use FacturaScripts\Core\Model\Subcuenta;
//use FacturaScripts\Plugins\SaldosPartidas\Extension\Model\Partida;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Plugins\SaldosPartidas\Model\Join\PartidaAsiento;

class Cron extends \FacturaScripts\Core\Base\CronClass
{
    public function run() 
    {
        $nameOfJob = 'FS_Calculate_Balances';
        if ($this->isTimeForJob($nameOfJob, "1 hours")) {
            $this->calculateBalances();
            $this->jobDone($nameOfJob);
        }
    }
    
    protected function calculateBalances() 
    {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();
        
        $ejercicios = new Ejercicio();

//error_log(var_dump($ejercicios), 3, "c:/jero/my-errors.log");
        
        foreach ($ejercicios->all() as $ejercicio) 
        {
            if (strtoupper(trim($ejercicio->estado)) !== 'ABIERTO') {
                continue;
            }
            
            if (false === $this->CalculateBalancesOfExercise($ejercicio)) {
                $dataBase->rollback();
                return;
            }
        }
        
        $dataBase->commit();
        return;
    }
    
    protected function CalculateBalancesOfExercise(Ejercicio $ejercicio): bool 
    {
        $subcuentas = new Subcuenta();
        
        foreach ($subcuentas->all([new DataBaseWhere('codejercicio', $ejercicio->codejercicio)]) as $subcuenta) 
        {
            if (false === $this->CalculateBalancesOfSubAcount($subcuenta)) {
                return false;
            }
        }
        
        return true;
    }

    protected function CalculateBalancesOfSubAcount(Subcuenta $subcuenta): bool 
    {
        $saldo = (float) 0.0;
        $partidasAsientos = new PartidaAsiento();

        foreach ( $partidasAsientos->all( [new DataBaseWhere('idsubcuenta', $subcuenta->idsubcuenta)] // Where
                                        , ['fecha' => 'ASC'] // Order
                                        , 0 // offset
                                        , 0 // limit
                                        ) as $partidaAsiento ) 
        {
            $saldo += ($partidaAsiento->debe - $partidaAsiento->haber);

            if ($saldo === $partidaAsiento->saldo)
            {
                continue;
            }

            $partida = new Partida();

            if (false === $partida->loadFromCode($partidaAsiento->idpartida)) 
            {
                return false; // Es imposible que no encuentre la partida en una transacción, pero por lo menos no anido ifs
            }

            if (false === $partida->save())
            {
                return false;
            }
        }

        return true;
    }

    
}

