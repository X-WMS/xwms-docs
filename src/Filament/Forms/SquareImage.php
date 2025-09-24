<?php

namespace XWMS\Package\Filament\Forms;

use Filament\Forms\Components\FileUpload;

class SquareImage
{
    public static function make(): FileUpload
    {
        return FileUpload::make('img')
            ->label('Image')
            ->image()
            ->maxSize(2048) // 2MB
            ->disk('public')
            ->imageEditor()
            ->directory('images')
            ->imageCropAspectRatio('1:1')
            ->imagePreviewHeight('180')
            ->imageResizeTargetWidth('400')
            ->imageResizeTargetHeight('400')
            ->helperText('The Image has to be an square and may not exceed 2mb.');
    }
}