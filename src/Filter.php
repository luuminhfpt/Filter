<?php

namespace VDVT\Filter;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use VDVT\Filter\Constants\References;

class Filter
{
    use \VDVT\Support\Traits\UserTimezoneTrait;

    /**
     * Helper draw data search
     * @author  TrinhLe
     * @return array
     */
    public function filtersSearchHelper(array $data, array $conditions): array
    {
        return array_filter(
            array_map(function ($config, $keyword) use ($data) {
                $searchKeyword = array_get($data, $keyword);

                if ($callbackFilterInput = array_get($config, 'callbackFilterInput')) {
                    $searchKeyword = $callbackFilterInput($searchKeyword);
                }

                if (isset($searchKeyword) && $searchKeyword !== '') {
                    /**
                     * Support Format DataType
                     *
                     * @author TrinhLe(trinh.le@bigin.vn)
                     */
                    if ($inputType = array_get($config, 'inputType')) {
                        switch ($inputType) {
                            case References::DATA_TYPE_DATE_TIME_ZONE:
                                $searchKeyword = Carbon::createFromFormat(
                                    'm/d/Y H:i:s',
                                    strtotime($searchKeyword),
                                    $this->getDefaultTimezone()
                                )->setTimezone(Config::get('app.timezone', 'UTC'));

                                if (array_get($config, 'is_end')) {
                                    $searchKeyword->addHours(24);
                                }
                                break;
                            case References::DATA_TYPE_INTEGER:
                                $searchKeyword = (int) $searchKeyword;
                                break;
                            case References::DATA_TYPE_BOOLEAN:
                                $searchKeyword = filter_var($searchKeyword, FILTER_VALIDATE_BOOLEAN);
                                break;
                        }
                    }

                    if ($callbackType = array_get($config, 'callbackType')) {
                        $searchKeyword = $callbackType($searchKeyword);
                        if (is_null($searchKeyword) || $searchKeyword == '') {
                            return;
                        }
                    }

                    /**
                     * Support Operator
                     *
                     * @author TrinhLe(trinh.le@bigin.vn)
                     */
                    $operator = array_get($config, 'operator', '=');

                    switch ($operator) {
                        case References::FILTER_OPERATOR_ILIKE:
                            $searchKeyword = "%{$searchKeyword}%";
                            break;
                    }

                    return [$config['column'], $operator, $searchKeyword];
                }
            }, $conditions, array_keys($conditions))
        );
    }

    /**
     * getSortString
     *
     * @param  array  $filters
     * @return string
     */
    public function getSortString(array $filters, array $options): string
    {
        $orderBy = array_get($filters, 'ascending') ? 'ASC' : 'DESC';
        $orderField = array_get($options, 'default', 'id');

        if (
            in_array(
                array_get($filters, 'orderBy', $orderField),
                array_get($options, 'columns')
            )
        ) {
            $orderField = array_get($filters, 'orderBy', $orderField);
        }

        return "{$orderField} {$orderBy}";
    }
}
