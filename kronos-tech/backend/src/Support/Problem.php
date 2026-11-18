<?php
namespace Kronos\Support;
final class Problem extends \RuntimeException {
    public function __construct(public int $status, string $message,public string $reason='invalid',public ?string $field=null){parent::__construct($message);}
}
