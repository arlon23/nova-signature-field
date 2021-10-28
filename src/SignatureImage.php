<?php

namespace Appstract\NovaSignatureField;

use Illuminate\Support\Str;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Http\Requests\NovaRequest;

class SignatureImage extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'nova-signature-field';

    public $disk;

    public $path;

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|callable|null  $attribute
     * @param  callable|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute = null, callable $resolveCallback = null)
    {
        $this->name = $name;
        $this->resolveCallback = $resolveCallback;

        $this->default(null);

        if ($attribute instanceof Closure ||
            (is_callable($attribute) && is_object($attribute))) {
            $this->computedCallback = $attribute;
            $this->attribute = 'ComputedField';
        } else {
            $this->attribute = $attribute ?? str_replace(' ', '_', Str::lower($name));
        }
    }

    public function fill(NovaRequest $request, $model)
    {
        return $this->fillAttribute($request, $this->attribute, $model, $this->attribute);
    }

    /**
     * Hydrate the given attribute on the model based on the incoming request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param string $requestAttribute
     * @param object $model
     * @param string $attribute
     *
     * @return void
     */
    protected function fillAttribute(NovaRequest $request, $requestAttribute, $model, $attribute)
    {
        $old_image = $model->{$attribute};

        $image = $request->{$requestAttribute};  // your base64 encoded
        $image = str_replace('data:image/png;base64,', '', $image);
        $image = str_replace(' ', '+', $image);
        $imageName = $this->path . '/' . str_random(25) . '.' . 'png';

        $this->value = $imageName;
        $this->valuew = $imageName;
        if (Storage::disk($this->disk)->put($imageName, base64_decode($image))) {
            $model->{$attribute} = $imageName;
            Storage::disk($this->disk)->delete($old_image);
        }
    }

    /**
     * Resolve the field's value.
     *
     * @param  mixed  $resource
     * @param  string|null  $attribute
     * @return void
     */
    public function resolve($resource, $attribute = null)
    {
        $this->resource = $resource;

        $attribute = $attribute ?? $this->attribute;

        if ($attribute === 'ComputedField') {
            $this->value = call_user_func($this->computedCallback, $resource);

            return;
        }

        if (! $this->resolveCallback) {
            $this->value = $this->resolveAttribute($resource, $attribute);
            $url = Storage::disk($this->disk)->url($this->value);

            $path_info = pathinfo($url);

            $filetype = 'jpg';

            if (array_key_exists('extension', $path_info)) {
                $filetype = $path_info['extension'];
            }

            try {
                $encoded_file = base64_encode(file_get_contents($url));
            } catch (\Exception $e) {
                return '';
            }

            $this->value = 'data:image/' . $filetype . ';base64,' . $encoded_file;
        } elseif (is_callable($this->resolveCallback)) {
            tap($this->resolveAttribute($resource, $attribute), function ($value) use ($resource, $attribute) {
                $this->value = call_user_func($this->resolveCallback, $value, $resource, $attribute);
            });
        }
    }

    public function path($path)
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Display field in modal.
     */
    public function editInModal($editInModal = true)
    {
        return $this->withMeta(['editInModal' => $editInModal]);
    }

    /**
     * Full width on detail.
     */
    public function fullWidthOnDetail($fullWidthOnDetail = true)
    {
        return $this->withMeta(['fullWidthOnDetail' => $fullWidthOnDetail]);
    }

    /**
     * Set the pad height
     */
    public function setPadHeight($padHeight = null)
    {
        return $this->withMeta(['padHeight' => $padHeight]);
    }

    /**
     * Set the name of the disk the file is stored on by default.
     *
     * @param  string  $disk
     * @return $this
     */
    public function disk($disk)
    {
        $this->disk = $disk;

        return $this;
    }

}
