<?php declare(strict_types = 1);

namespace Vairogs\Utils\Doctrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\ORM\Query\SqlWalker;
use InvalidArgumentException;
use function func_get_args;
use function is_array;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;

class SortableNullWalker extends SqlWalker
{
    final public const NULLS_FIRST = 'NULLS FIRST';
    final public const NULLS_LAST = 'NULLS LAST';
    final public const FIELDS = self::class . '.fields';

    /**
     * @throws Exception
     */
    public function walkOrderByClause($orderByClause): string|array|null
    {
        $sql = parent::walkOrderByClause(orderByClause: $orderByClause);

        // @formatter:off
        $fields = $this->getQuery()->getHint(name: self::FIELDS);
        $platform = $this->getConnection()?->getDatabasePlatform()?->getName();
        // @formatter:on

        $mysql = (new MySqlPlatform())->getName();
        $postgres = (new PostgreSQL100Platform())->getName();
        $oracle = (new PostgreSQL100Platform())->getName();

        if (is_array(value: $fields) && null !== $platform) {
            foreach ($fields as $field => $sorting) {
                $sql = match ($platform) {
                    $mysql => $this->stepMysql(sql: $sql, field: $field, sorting: $sorting),
                    $oracle => $this->stepOracle(sql: $sql, field: $field, sorting: $sorting),
                    $postgres => $this->stepPostgre(sql: $sql, field: $field, sorting: $sorting),
                    default => throw new InvalidArgumentException(message: sprintf('Walker not implemented for "%s" platform', $platform)),
                };
            }
        }

        return $sql;
    }

    private function stepMysql(string $sql, string $field, string $sorting): string
    {
        if (self::NULLS_LAST === $sorting) {
            return preg_replace_callback(pattern: '/ORDER BY (.+)' . '(' . $field . ') (' . Criteria::ASC . '|' . Criteria::DESC . ')/i', callback: static function ($matches): string {
                if (Criteria::ASC === $matches[3]) {
                    $order = Criteria::DESC;
                } elseif (Criteria::DESC === $matches[3]) {
                    $order = Criteria::ASC;
                } else {
                    throw new InvalidArgumentException(message: sprintf('Order must be "%s" or "%s"', Criteria::ASC, Criteria::DESC));
                }

                return 'ORDER BY -' . $matches[1] . $matches[2] . ' ' . $order;
            }, subject: $sql);
        }

        return $sql;
    }

    private function stepOracle(string $sql, string $field, string $sorting): string
    {
        return preg_replace(pattern: '/(\.' . $field . ') (' . Criteria::ASC . '|' . Criteria::DESC . ')?\s*/i', replacement: '$1 $2 ' . $sorting, subject: $sql);
    }

    private function stepPostgre(string $sql, string $field, string $sorting): string
    {
        return $this->stepOracle(...func_get_args());
    }
}
