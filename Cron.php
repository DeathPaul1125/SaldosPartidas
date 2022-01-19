<?php

namespace FacturaScripts\Plugins\SaldosPartidas;

use FacturaScripts\Core\Base\DataBase;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Model\Ejercicio;
use FacturaScripts\Core\Model\Subcuenta;
//use FacturaScripts\Plugins\SaldosPartidas\Extension\Model\Partida;
use FacturaScripts\Dinamic\Model\Partida;
use FacturaScripts\Plugins\SaldosPartidas\Model\Join\PartidaAsiento;

class Cron extends \FacturaScripts\Core\Base\CronClass {

    const JOB_NAME = 'Calculate_Balances';

    public function run() {
        if ($this->isTimeForJob(self::JOB_NAME, "1 hours")) {
            $this->calculateBalances();
            $this->jobDone(self::JOB_NAME);
        }
    }

    protected function calculateBalances() {
        $dataBase = new DataBase();
        $dataBase->beginTransaction();

        $ejercicioModel = new Ejercicio();

        foreach ($ejercicioModel->all() as $ejercicio) {
            if (!$ejercicio->isOpened()) {
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

    protected function CalculateBalancesOfExercise(Ejercicio $ejercicio): bool {
        $subcuentaModel = new Subcuenta();
        $where = [new DataBaseWhere('codejercicio', $ejercicio->codejercicio)];
        foreach ($subcuentaModel->all($where, [], 0, 0) as $subcuenta) {
            if (false === $this->CalculateBalancesOfSubAcount($subcuenta)) {
                return false;
            }
        }

        return true;
    }

    protected function CalculateBalancesOfSubAcount(Subcuenta $subcuenta): bool {
        $partidasAsientoModel = new PartidaAsiento();
        $where = [new DataBaseWhere('idsubcuenta', $subcuenta->idsubcuenta)];
        $order = ['fecha' => 'ASC'];

        $saldo = (float) 0.0;

        foreach ($partidasAsientoModel->all($where, $order, 0, 0) as $partidaAsiento) {
            $saldo += $partidaAsiento->debe - $partidaAsiento->haber;

            if ($saldo === $partidaAsiento->saldo) {
                continue;
            }

            $partida = new Partida();

            if (false === $partida->loadFromCode($partidaAsiento->idpartida)) {
                return false; // Es imposible que no encuentre la partida en una transacción, pero por lo menos no anido ifs
            }
            
            $partida->saldo = $saldo;
            
            if (false === $partida->save()) {
                return false;
            }
        }

        return true;
    }

}
