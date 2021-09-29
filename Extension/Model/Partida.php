<?php
namespace FacturaScripts\Plugins\SaldosPartidas\Extension\Model;

class Partida
{

    /**
     * Balance of a subaccount.
     *
     * @var float|int
     */
    public $saldo;
    
    
    // clear()
    public function clear() {
        return function() {
            $this->saldo = 0.0;
        };
    }
    
    // save() se ejecuta una vez realizado el save() del modelo.
    public function save() {
        return function() {
            // Aquí pondremos el código para la tarea que irá actualizando en un futuro a las subcuentas su saldo
            // O igual habría que hacerlo en el beforeSave ... ya comentará Carlos donde
        };
    }
    

}
