<?php

namespace App\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/** Counts Doctrine's queries in QueryCounter, like the legacy Db does. */
final class QueryCountingMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            public function connect(#[\SensitiveParameter] array $params): Connection
            {
                return new class (parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        return new class (parent::prepare($sql), $sql) extends AbstractStatementMiddleware {
                            public function __construct(Statement $statement, private readonly string $sql)
                            {
                                parent::__construct($statement);
                            }

                            public function execute(): Result
                            {
                                return QueryCounter::time(fn(): Result => parent::execute(), $this->sql);
                            }
                        };
                    }

                    public function query(string $sql): Result
                    {
                        return QueryCounter::time(fn(): Result => parent::query($sql), $sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        return QueryCounter::time(fn(): int|string => parent::exec($sql), $sql);
                    }
                };
            }
        };
    }
}
