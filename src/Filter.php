<?php

namespace VDVT\Filter;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Config;
use VDVT\Filter\Constants\References;

class Filter
{
    use \VDVT\Support\Traits\UserTimezoneTrait;

    /**
     * HelperMigrate draw data search
     * @return array
     * @author  TrinhLe
     */
    public function filtersSearchHelper(array $data, array $conditions, &$builder = null): array
    {
        return array_filter(
            array_map(function ($config, $keyword) use ($data, &$builder) {
                $searchKeyword = data_get($data, $keyword);
                $column = data_get($config, 'column');

                if ($callbackFilterInput = data_get($config, 'callbackFilterInput')) {
                    $searchKeyword = $callbackFilterInput($searchKeyword);
                }

                if (isset($searchKeyword) && $searchKeyword !== '') {
                    /**
                     * Support Format DataType
                     *
                     * @author TrinhLe(trinh.le@bigin.vn)
                     */
                    if ($inputType = data_get($config, 'inputType')) {
                        switch ($inputType) {
                            case References::DATA_TYPE_DATE_TIME_ZONE:
                                $searchKeyword = Carbon::createFromTimestamp(
                                    strtotime($searchKeyword),
                                    $this->getDefaultTimezone()
                                )->setTimezone(Config::get('app.timezone', 'UTC'));

                                if (data_get($config, 'is_end')) {
                                    $searchKeyword->addHours(24);
                                }
                                break;
                            case References::DATA_TYPE_INTEGER:
                                $searchKeyword = (int)$searchKeyword;
                                break;
                            case References::DATA_TYPE_BOOLEAN:
                                $searchKeyword = filter_var($searchKeyword, FILTER_VALIDATE_BOOLEAN);
                                break;
                        }
                    }

                    if ($callbackType = data_get($config, 'callbackType')) {
                        $searchKeyword = $callbackType($searchKeyword);
                        if (
                            is_null($searchKeyword) ||
                            $searchKeyword === ''
                        ) {
                            return;
                        }
                    }

                    if (data_get($config, 'isStrToLower')) {
                        $searchKeyword = strtolower($searchKeyword);
                    }

                    /**
                     * Support Operator
                     *
                     * @author TrinhLe(trinh.le@bigin.vn)
                     */
                    $operator = data_get($config, 'operator', '=');

                    switch ($operator) {
                        case References::FILTER_OPERATOR_ILIKE:
                            $searchKeyword = "%{$searchKeyword}%";
                            break;
                        case References::FILTER_OPERATOR_IN:
                            if ($builder instanceof EloquentBuilder || $builder instanceof QueryBuilder) {
                                $builder->whereIn($column, is_array($searchKeyword) ? $searchKeyword : []);
                                return;
                            }
                    }

                    if (is_array($column)) {
                        if ($builder instanceof EloquentBuilder || $builder instanceof QueryBuilder) {
                            $builder
                                ->where(function ($query) use ($column, $operator, $searchKeyword) {
                                    foreach ($column as $queryColumn) {
                                        $query->orWhere($queryColumn, $operator, $searchKeyword);
                                    }
                                });
                        }
                        return;
                    }

                    return [$column, $operator, $searchKeyword];
                }
            }, $conditions, array_keys($conditions))
        );
    }

    /**
     * getSortString
     *
     * @param array $filters
     * @return string
     */
    public function getSortString(array $filters, array $options): string
    {
        $orderBy = data_get($filters, 'ascending') ? 'ASC' : 'DESC';
        $orderField = data_get($options, 'default', 'id');

        if (
        in_array(
            data_get($filters, 'orderBy', $orderField),
            data_get($options, 'columns')
        )
        ) {
            $orderField = data_get($filters, 'orderBy', $orderField);
        }

        return "{$orderField} {$orderBy}";
    }
}
