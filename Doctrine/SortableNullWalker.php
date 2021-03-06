<?php declare(strict_types = 1);

namespace Vairogs\Component\Utils\Doctrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\ORM\Query\AST\OrderByClause;
use Doctrine\ORM\Query\SqlWalker;
use InvalidArgumentException;
use function is_array;
use function preg_replace;
use function preg_replace_callback;
use function sprintf;

class SortableNullWalker extends SqlWalker
{
    /**
     * @var string
     */
    public const NULLS_FIRST = 'NULLS FIRST';
    /**
     * @var string
     */
    public const NULLS_LAST = 'NULLS LAST';
    /**
     * @var string
     */
    public const FIELDS = self::class . '.fields';

    /**
     * @param OrderByClause $orderByClause
     * @return string|string[]|null
     * @throws Exception
     * @noinspection PhpMissingParamTypeInspection
     */
    public function walkOrderByClause($orderByClause): array|string|null
    {
        $sql = parent::walkOrderByClause($orderByClause);

        if (is_array($fields = $this->getQuery()
                ->getHint(self::FIELDS)) && $platform = $this->getConnection()
                ->getDatabasePlatform()) {
            switch ($platform->getName()) {
                case (new MySqlPlatform())->getName():
                    foreach ($fields as $field => $sorting) {
                        if (self::NULLS_LAST === $sorting) {
                            $sql = preg_replace_callback('/ORDER BY (.+)' . '(' . $field . ') (ASC|DESC)/i', static function ($matches): string {
                                if (Criteria::ASC === $matches[3]) {
                                    $order = Criteria::DESC;
                                } elseif (Criteria::DESC === $matches[3]) {
                                    $order = Criteria::ASC;
                                } else {
                                    throw new InvalidArgumentException(sprintf('Order must be "%s" or "%s"', Criteria::ASC, Criteria::DESC));
                                }

                                return ('ORDER BY -' . $matches[1] . $matches[2] . ' ' . $order);
                            }, $sql);
                        }
                    }
                    break;
                case (new OraclePlatform())->getName():
                case (new PostgreSQL100Platform())->getName():
                    foreach ($fields as $field => $sorting) {
                        $sql = preg_replace('/(\.' . $field . ') (ASC|DESC)?\s*/i', '$1 $2 ' . $sorting, $sql);
                    }
                    break;
                default:
                    throw new InvalidArgumentException(sprintf('Walker not implemented for "%s" platform', $platform));
            }
        }

        return $sql;
    }
}
