<?php

namespace Admin\Model;

class ContentCategoriesModel extends AbstractCrudModel
{
    protected string $table = 'content_categories';
    protected array $fillable = ['name', 'slug', 'description', 'status'];
}
