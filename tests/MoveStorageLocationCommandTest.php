<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Concrete\Core\File\Command\FileCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use AwsStorage\Command\MoveStorageLocationCommand;
use AwsStorage\Command\MoveStorageLocationCommandHandler;

#[CoversClass(MoveStorageLocationCommand::class)]
#[CoversClass(MoveStorageLocationCommandHandler::class)]
class MoveStorageLocationCommandTest extends TestCase
{
    public function testCommandStoresTheFileAndStorageLocationIds(): void
    {
        $command = new MoveStorageLocationCommand(404, 909);

        $this->assertSame(404, $command->getFileID());
        $this->assertSame(909, $command->getStorageLocationID());
        $this->assertInstanceOf(FileCommand::class, $command);
        $this->assertSame(MoveStorageLocationCommandHandler::class, $command::getHandler());
    }
}
