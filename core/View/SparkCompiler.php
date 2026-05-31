<?php
declare(strict_types=1);

namespace Flint\View;

class SparkCompiler
{
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = $viewsPath;
    }

    public function compile(string $source, string $sourcePath = ''): string
    {
        if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $source, $extMatch)) {
            $source = $this->compileWithLayout($source, $extMatch[1]);
        }

        return $this->compileDirectives($source);
    }

    private function compileWithLayout(string $source, string $layout): string
    {
        $source = preg_replace('/@extends\s*\(\s*[\'"][^\'"]+[\'"]\s*\)\s*\n?/', '', $source);

        $sections = [];
        $source = preg_replace_callback(
            '/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*(.*?)@endsection/s',
            function (array $m) use (&$sections): string {
                $sections[$m[1]] = $m[2];
                return '';
            },
            $source
        );

        $layoutPath = $this->viewsPath . '/' . str_replace('.', '/', $layout) . '.spark.php';
        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Spark layout not found: {$layout} ({$layoutPath})");
        }
        $layoutSource = file_get_contents($layoutPath);

        $merged = preg_replace_callback(
            '/@yield\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            fn(array $m) => $sections[$m[1]] ?? '',
            $layoutSource
        );

        return $this->compileDirectives($merged);
    }

    private function compileDirectives(string $source): string
    {
        // Raw (unescaped) echo: {!! expr !!}
        $source = preg_replace('/\{!!\s*(.*?)\s*!!\}/s', '<?php echo $1; ?>', $source);

        // Escaped echo: {{ expr }}
        $source = preg_replace(
            '/\{\{\s*(.*?)\s*\}\}/s',
            '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>',
            $source
        );

        // @php ... @endphp
        $source = preg_replace('/@php(.*?)@endphp/s', '<?php$1?>', $source);

        // @if / @elseif / @else / @endif
        $source = preg_replace('/@if\s*\((.+?)\)/', '<?php if ($1): ?>', $source);
        $source = preg_replace('/@elseif\s*\((.+?)\)/', '<?php elseif ($1): ?>', $source);
        $source = preg_replace('/@else\b/', '<?php else: ?>', $source);
        $source = preg_replace('/@endif\b/', '<?php endif; ?>', $source);

        // @foreach / @endforeach
        $source = preg_replace('/@foreach\s*\((.+?)\)/', '<?php foreach ($1): ?>', $source);
        $source = preg_replace('/@endforeach\b/', '<?php endforeach; ?>', $source);

        // @for / @endfor
        $source = preg_replace('/@for\s*\((.+?)\)/', '<?php for ($1): ?>', $source);
        $source = preg_replace('/@endfor\b/', '<?php endfor; ?>', $source);

        // @while / @endwhile
        $source = preg_replace('/@while\s*\((.+?)\)/', '<?php while ($1): ?>', $source);
        $source = preg_replace('/@endwhile\b/', '<?php endwhile; ?>', $source);

        // @auth / @endauth / @guest / @endguest
        $source = preg_replace('/@auth\b/', '<?php if ($auth->check()): ?>', $source);
        $source = preg_replace('/@endauth\b/', '<?php endif; ?>', $source);
        $source = preg_replace('/@guest\b/', '<?php if ($auth->guest()): ?>', $source);
        $source = preg_replace('/@endguest\b/', '<?php endif; ?>', $source);

        // @csrf
        $source = preg_replace('/@csrf\b/', '<?php echo csrf_field(); ?>', $source);

        // @method('PUT') — HTML method spoofing
        $source = preg_replace(
            '/@method\s*\(\s*[\'"]([A-Z]+)[\'"]\s*\)/',
            '<input type="hidden" name="_method" value="$1">',
            $source
        );

        // @old('field')
        $source = preg_replace(
            '/@old\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/',
            '<?php echo htmlspecialchars((string)(old(\'$1\', \'\')), ENT_QUOTES, \'UTF-8\'); ?>',
            $source
        );

        // @error('field') ... @enderror
        $source = preg_replace_callback(
            '/@error\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)(.*?)@enderror/s',
            function (array $m): string {
                $field = $m[1];
                $inner = $m[2];
                return "<?php if (isset(\$errors['{$field}'])): \$message = is_array(\$errors['{$field}']) ? \$errors['{$field}'][0] : \$errors['{$field}']; ?>{$inner}<?php endif; ?>";
            },
            $source
        );

        // @include('view.name') or @include('view.name', ['key' => 'val'])
        $source = preg_replace_callback(
            '/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+?))?\s*\)/',
            function (array $m): string {
                $view = $m[1];
                $data = isset($m[2]) && $m[2] !== '' ? $m[2] : '[]';
                return "<?php echo \$__spark->render('{$view}', array_merge(get_defined_vars(), {$data})); ?>";
            },
            $source
        );

        // @dump($var)
        $source = preg_replace('/@dump\s*\((.+?)\)/', '<?php var_dump($1); ?>', $source);

        return $source;
    }
}
