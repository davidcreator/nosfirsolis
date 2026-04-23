<?php

namespace Admin\Model;

class LanguagesModel extends AbstractCrudModel
{
    protected string $table = 'languages';
    protected array $fillable = ['code', 'name', 'is_default', 'status'];
}
