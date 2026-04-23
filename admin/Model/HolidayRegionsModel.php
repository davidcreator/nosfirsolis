<?php

namespace Admin\Model;

class HolidayRegionsModel extends AbstractCrudModel
{
    protected string $table = 'holiday_regions';
    protected array $fillable = ['name', 'country_code', 'state_code', 'region_type', 'status'];
}
