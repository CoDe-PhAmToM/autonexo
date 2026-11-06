<?php

namespace App\Exceptions;

use RuntimeException;

/** Se lanza al intentar editar o borrar un registro que solo puede anularse (PBI-20). */
class RegistroInmutable extends RuntimeException
{
}
