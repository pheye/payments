<?php
namespace Pheye\Payments\Exceptions;

class BusinessErrorException extends \ErrorException
{
    public $errors;

    public function __construct(string $desc, int $code = -1, array $errors = []) {
        parent::__construct($desc, $code);
        $this->errors = $errors;
    }
}
