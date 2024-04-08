<?php declare(strict_types=1);

namespace HistoryLog\Api\Adapter;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\QueryBuilder;

trait BuildQueryTrait
{
    /**
     * Allow to use event queries for events and changes.
     *
     * Furthermore, avoid to join the related entity multiple times.
     *
     * Require that the fields are not the same on the two events, else pass the
     *  query type fields.
     *
     * "id" is managed outside.
     *
     * @return bool True if there is a query on a metadata.
     */
    protected function buildQueryFields(
        QueryBuilder $qb,
        array $query,
        ?string $entityAlias = null,
        ?array $queryTypeFields = null
    ): bool {
        $hasQueryField = false;

        $entityAlias ??= 'omeka_root';
        $queryFields = $queryTypeFields ?? $this->queryTypeFields ?? [];

        $expr = $qb->expr();

        foreach (array_intersect_key($queryFields, $query) as $fieldName => $argType) {
            if ($query[$fieldName] === null || $query[$fieldName] === '' || $query[$fieldName] === []) {
                continue;
            }
            // Some query type fields are not scalar fields.
            $field = $this->scalarFields[$fieldName] ?? null;
            switch ($argType) {
                default:
                case 'id':
                case 'string':
                    if (!is_array($query[$fieldName])) {
                        $query[$fieldName] = [$query[$fieldName]];
                    }
                    $values = array_values(array_unique(array_map($argType === 'id' ? 'intval' : 'strval', $query[$fieldName])));
                    if (count($values) <= 1) {
                        $qb
                            ->andWhere($expr->eq(
                                "omeka_root.$field",
                                $this->createNamedParameter($qb, reset($values))
                            ));
                    } else {
                        $valuesAlias = $this->createAlias();
                        $qb
                            ->andWhere($expr->in("omeka_root.$field", ":$valuesAlias"))
                            ->setParameter($valuesAlias, $values, Connection::PARAM_STR_ARRAY);
                    }
                    $hasQueryField = true;
                    break;

                case 'boolean':
                    if (is_numeric($query[$fieldName]) || is_bool($query[$fieldName])) {
                        $qb
                            ->andWhere($expr->eq(
                                "omeka_root.$field",
                                $this->createNamedParameter($qb, (bool) $query[$argType])
                            ));
                        $hasQueryField = true;
                    }
                    break;

                case 'datetime':
                    /** @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildQuery() */
                    // @TODO See log for a simpler and more complete doctrine search.
                    // In Omeka Classic, used "since" and "until".
                    $dateSearches = [
                        'created' => ['eq', 'created'],
                        'created_before' => ['lt', 'created'],
                        'created_after' => ['gt', 'created'],
                        'created_before_on' => ['lte', 'created'],
                        'created_after_on' => ['gte', 'created'],
                        'created_until' => ['lte', 'created'],
                        'created_since' => ['gte', 'created'],
                        'modified' => ['eq', 'modified'],
                        'modified_before' => ['lt', 'modified'],
                        'modified_before_on' => ['lte', 'modified'],
                        'modified_after' => ['gt', 'modified'],
                        'modified_after_on' => ['gte', 'modified'],
                        'modified_until' => ['lte', 'modified'],
                        'modified_since' => ['gte', 'modified'],
                    ];
                    $dateGranularities = [
                        DateTime::ISO8601,
                        '!Y-m-d\TH:i:s',
                        '!Y-m-d\TH:i',
                        '!Y-m-d\TH',
                        '!Y-m-d',
                        '!Y-m',
                        '!Y',
                    ];
                    $fieldDate = $dateSearches[$fieldName] ?? null;
                    if (!$fieldDate) {
                        break;
                    }
                    foreach ($dateGranularities as $dateGranularity) {
                        $date = DateTime::createFromFormat($dateGranularity, $query[$fieldName]);
                        if (false !== $date) {
                            break;
                        }
                    }
                    $qb
                        ->andWhere($expr->{$fieldDate[0]} (
                            $entityAlias . '.' . $fieldDate[1],
                            // If the date is invalid, pass null to ensure no results.
                            $this->createNamedParameter($qb, $date ?: null)
                        ));
                    $hasQueryField = true;
                    break;
            }
        }

        return $hasQueryField;
    }
}
