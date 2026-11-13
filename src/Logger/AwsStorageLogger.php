<?php

namespace AwsStorage\Logger;

use ClassKit\Log\Logger;

class AwsStorageLogger extends Logger
{
    public function __construct()
    {
        parent::__construct('aws_storage');
    }
}
