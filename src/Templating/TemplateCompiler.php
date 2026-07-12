<?php

namespace Novalites\Templating;

use Novalites\Exception\TemplateException;

class TemplateCompiler
{
    protected int $forelseCounter = 0;

    public function compile(string $content): string
    {
        $this->forelseCounter = 0;

        $content = $this->compileComments($content);
        $content = $this->compileRawEcho($content);
        $content = $this->compileEscapedEcho($content);

        $content = $this->compileForelse($content); // WAJIB sebelum foreach
        $content = $this->compileWrappedDirective($content, 'foreach', fn($e) => "<?php foreach({$e}): ?>");
        $content = $this->compileWrappedDirective($content, 'for', fn($e) => "<?php for({$e}): ?>");
        $content = $this->compileWrappedDirective($content, 'while', fn($e) => "<?php while({$e}): ?>");
        $content = $this->compileWrappedDirective($content, 'if', fn($e) => "<?php if({$e}): ?>");
        $content = $this->compileWrappedDirective($content, 'elseif', fn($e) => "<?php elseif({$e}): ?>");
        $content = $this->compileWrappedDirective($content, 'unless', fn($e) => "<?php if(!({$e})): ?>");
        $content = $this->compileWrappedDirective($content, 'isset', fn($e) => "<?php if(isset({$e})): ?>");
        $content = $this->compileWrappedDirective($content, 'empty', fn($e) => "<?php if(empty({$e})): ?>");
        $content = $this->compileWrappedDirective($content, 'extends', fn($e) => "<?php \$this->extendsLayout({$e}) ?>");
        $content = $this->compileWrappedDirective($content, 'include', fn($e) => "<?= \$this->includeView({$e}) ?>");
        $content = $this->compileWrappedDirective($content, 'yield', fn($e) => "<?= \$this->yieldSection({$e}) ?>");
        $content = $this->compileWrappedDirective($content, 'stack', fn($e) => "<?= \$this->yieldStack({$e}) ?>");   // ← baru
        $content = $this->compileWrappedDirective($content, 'push', fn($e) => "<?php \$this->startPush({$e}) ?>");   // ← baru
        $content = $this->compileWrappedDirective($content, 'prepend', fn($e) => "<?php \$this->startPrepend({$e}) ?>"); // ← baru
        $content = $this->compileWrappedDirective($content, 'method', fn($e) => '<input type="hidden" name="_method" value="' . trim($e, "'\" ") . '">');
        $content = $this->compileWrappedDirective(
            $content,
            'dd',
            fn($e) =>
            '<?php echo "<pre style=\'background:#1e1e1e;color:#d4d4d4;padding:1rem;border-radius:6px;\'>"; var_dump(' . $e . '); echo "</pre>"; exit; ?>'
        );
        $content = $this->compileSection($content);

        $content = $this->compileSimpleMarkers($content);
        $content = $this->compilePhp($content);

        return $content;
    }

    protected function compileComments(string $content): string
    {
        return preg_replace('/\[\[--(.+?)--\]\]/s', '', $content);
    }

    protected function compileRawEcho(string $content): string
    {
        return preg_replace('/\[\[\[\s*(.+?)\s*\]\]\]/s', '<?= $1 ?>', $content);
    }

    protected function compileEscapedEcho(string $content): string
    {
        return preg_replace('/\[\[\s*(.+?)\s*\]\]/s', '<?= e($1) ?>', $content);
    }

    /**
     * Directive tanpa kurung sama sekali: &else, &endif, &endforeach, &auth, dll.
     */
    protected function compileSimpleMarkers(string $content): string
    {
        $map = [
            '&endif'       => '<?php endif; ?>',
            '&else'        => '<?php else: ?>',
            '&endforeach'  => '<?php endforeach; ?>',
            '&endfor'      => '<?php endfor; ?>',
            '&endwhile'    => '<?php endwhile; ?>',
            '&endunless'   => '<?php endif; ?>',
            '&endisset'    => '<?php endif; ?>',
            '&endempty'    => '<?php endif; ?>',
            '&endsection'  => '<?php $this->stopSection() ?>',
            '&push'     => '<?php $this->startPush() ?>',    // ← baru
            '&endpush'     => '<?php $this->stopPush() ?>',    // ← baru
            '&endprepend'  => '<?php $this->stopPrepend() ?>', // ← baru
            '&show'        => '<?= $this->stopSection(true) ?>',
            '&auth'        => '<?php if(\Novalites\Auth\Auth::check()): ?>',
            '&endauth'     => '<?php endif; ?>',
            '&guest'       => '<?php if(\Novalites\Auth\Auth::guest()): ?>',
            '&endguest'    => '<?php endif; ?>',
            '&csrf'        => '<?= \'<input type="hidden" name="_token" value="\' . e(\Novalites\Security\CsrfToken::get()) . \'">\' ?>',
        ];

        // Urutkan dari yang paling panjang biar '&endif' ga ke-cut duluan sama '&if' dkk
        uksort($map, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($map as $needle => $replacement) {
            $content = $this->replaceBareMarker($content, $needle, $replacement);
        }

        return $content;
    }

    /**
     * Ganti marker yang BUKAN diikuti '(' dan BUKAN bagian dari kata lain
     * (misal '&if' ga boleh kena replace kalau sebenarnya '&isset').
     */
    protected function replaceBareMarker(string $content, string $needle, string $replacement): string
    {
        $pos = 0;
        $len = strlen($needle);

        while (($found = strpos($content, $needle, $pos)) !== false) {
            $after = $found + $len;
            $nextChar = $content[$after] ?? '';

            // Valid marker kalau abis needle bukan huruf/angka/underscore (biar '&if' ga nyantol ke '&iffy' dsb)
            // dan bukan diikuti '(' (kalau ada '(' berarti itu directive lain yang beda, bukan bare marker)
            if (ctype_alnum($nextChar) || $nextChar === '_' || $nextChar === '(') {
                $pos = $found + $len;
                continue;
            }

            $content = substr($content, 0, $found) . $replacement . substr($content, $after);
            $pos = $found + strlen($replacement);
        }

        return $content;
    }

    /**
     * Directive dengan kurung: cari '&nama(', ambil isi dengan BALANCED PAREN COUNTING
     * (bukan regex non-greedy yang rawan salah potong kalau ada nested parens).
     */
    protected function compileWrappedDirective(string $content, string $directive, callable $template): string
    {
        $needle = '&' . $directive;
        $pos = 0;

        while (($found = strpos($content, $needle, $pos)) !== false) {
            $after = $found + strlen($needle);

            // Cek karakter setelah keyword — kalau masih huruf/angka, ini bukan match yang valid
            // (misal '&for' ga boleh nyangkut ke '&forelse'/'&foreach')
            $nextChar = $content[$after] ?? '';
            if (ctype_alnum($nextChar) || $nextChar === '_') {
                $pos = $found + strlen($needle);
                continue;
            }

            // Skip whitespace sampai ketemu '('
            $peek = $after;
            while (isset($content[$peek]) && ctype_space($content[$peek])) {
                $peek++;
            }

            if (!isset($content[$peek]) || $content[$peek] !== '(') {
                $pos = $found + strlen($needle);
                continue;
            }

            [$expr, $afterParen] = $this->extractBalancedParens($content, $peek);

            $replacement = $template(trim($expr));

            $content = substr($content, 0, $found) . $replacement . substr($content, $afterParen);
            $pos = $found + strlen($replacement);
        }

        return $content;
    }

    /**
     * Ambil isi di dalam kurung dengan menghitung depth '(' dan ')',
     * jadi nested function call kayak count($users) ga bikin salah potong.
     */
    protected function extractBalancedParens(string $content, int $openParenPos): array
    {
        $len = strlen($content);
        $depth = 0;
        $inString = null; // null | "'" | '"'

        for ($i = $openParenPos; $i < $len; $i++) {
            $char = $content[$i];

            // Skip karakter di dalam string literal, biar kurung di dalam string ga dihitung
            if ($inString !== null) {
                if ($char === $inString && $content[$i - 1] !== '\\') {
                    $inString = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $expr = substr($content, $openParenPos + 1, $i - $openParenPos - 1);
                    return [$expr, $i + 1];
                }
            }
        }

        throw new TemplateException("Tanda kurung tidak seimbang, dimulai di posisi {$openParenPos}.");
    }

    /**
     * &forelse($items as $item) ... &empty ... &endforelse
     * Pakai stack biar support nested forelse.
     */
    protected function compileForelse(string $content): string
    {
        $result = '';
        $pos = 0;
        $len = strlen($content);
        $stack = [];

        while ($pos < $len) {
            $nextForelse = strpos($content, '&forelse', $pos);
            $nextEmpty = $this->findBareEmpty($content, $pos);
            $nextEndforelse = strpos($content, '&endforelse', $pos);

            $candidates = [];
            if ($nextForelse !== false) $candidates['forelse'] = $nextForelse;
            if ($nextEmpty !== false) $candidates['empty'] = $nextEmpty;
            if ($nextEndforelse !== false) $candidates['endforelse'] = $nextEndforelse;

            if (empty($candidates)) {
                $result .= substr($content, $pos);
                break;
            }

            $nextPos = min($candidates);
            $nextType = array_search($nextPos, $candidates, true);

            $result .= substr($content, $pos, $nextPos - $pos);

            if ($nextType === 'forelse') {
                $afterKeyword = $nextPos + strlen('&forelse');
                $peek = $afterKeyword;
                while (isset($content[$peek]) && ctype_space($content[$peek])) $peek++;

                [$expr, $afterParen] = $this->extractBalancedParens($content, $peek);

                $var = '$__forelse_' . $this->forelseCounter++;
                $result .= "<?php {$var} = true; foreach({$expr}): {$var} = false; ?>";
                $stack[] = $var;
                $pos = $afterParen;
            } elseif ($nextType === 'empty') {
                $var = end($stack) ?: '$__forelse_unknown';
                $result .= "<?php endforeach; if({$var}): ?>";
                $pos = $nextPos + strlen('&empty');
            } else {
                array_pop($stack);
                $result .= '<?php endif; ?>';
                $pos = $nextPos + strlen('&endforelse');
            }
        }

        return $result;
    }

    /**
     * Cari '&empty' yang BUKAN directive berkurung (&empty(...)),
     * ini yang dipakai sebagai marker di dalam blok &forelse.
     */
    protected function findBareEmpty(string $content, int $offset): int|false
    {
        $pos = $offset;

        while (($found = strpos($content, '&empty', $pos)) !== false) {
            $after = $found + strlen('&empty');
            $nextChar = $content[$after] ?? '';

            if ($nextChar !== '(') {
                return $found;
            }

            $pos = $after;
        }

        return false;
    }

    /**
     * &section('name') ... &endsection   -> block
     * &section('name', 'value')          -> inline
     */
    protected function compileSection(string $content): string
    {
        return $this->compileWrappedDirective($content, 'section', function ($expr) {
            $parts = $this->splitTopLevelComma($expr);

            if (count($parts) >= 2) {
                $name = trim($parts[0]);
                $value = trim(implode(',', array_slice($parts, 1)));
                return "<?php \$this->section({$name}, {$value}) ?>";
            }

            return "<?php \$this->startSection({$expr}) ?>";
        });
    }

    /**
     * Split string by comma, tapi ga masuk ke comma yang ada di dalam kurung/string literal.
     */
    protected function splitTopLevelComma(string $expr): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inString = null;
        $len = strlen($expr);

        for ($i = 0; $i < $len; $i++) {
            $char = $expr[$i];

            if ($inString !== null) {
                $current .= $char;
                if ($char === $inString && ($expr[$i - 1] ?? '') !== '\\') {
                    $inString = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
                $current .= $char;
                continue;
            }

            if ($char === '(' || $char === '[') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === ')' || $char === ']') {
                $depth--;
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if ($current !== '') {
            $parts[] = $current;
        }

        return $parts;
    }

    protected function compilePhp(string $content): string
    {
        $content = $this->replaceBareMarker($content, '&php', '<?php');
        $content = $this->replaceBareMarker($content, '&endphp', '?>');

        return $content;
    }
}
