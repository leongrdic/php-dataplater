<?php

$tests = [
    [
        'nesto, nesto2,nesto3 , nes(ovo u biti, ne smije)',
        ['nesto', 'nesto2', 'nesto3', 'nes(ovoubiti,nesmije)'],
    ],
    [
        'nesto, nesto2,nesto3 , nes(ovo u biti,( ne smije) proci)',
        ['nesto', 'nesto2', 'nesto3', 'nes(ovoubiti,(nesmije)proci)'],
    ],
    [
        'nesto, nes"to2,ne"sto3 , nes(ovo u biti,( ne smije) proci)',
        ['nesto', 'nes"to2,ne"sto3', 'nes(ovoubiti,(nesmije)proci)'],
    ],
    [
        'nesto, nes"to2, neki escaped quote \", ne"sto3 , nes(ovo u biti,( ne smije) proci)',
        ['nesto', 'nes"to2, neki escaped quote \", ne"sto3', 'nes(ovoubiti,(nesmije)proci)'],
    ],
    [
        'nesto, nes"to2, neki escaped quote \", ne"sto3 , nes(ovo u" biti,( ne smije) proci")',
        ['nesto', 'nes"to2, neki escaped quote \", ne"sto3', 'nes(ovou" biti,( ne smije) proci")'],
    ],
    [
        'nesto, nes"to2, neki escaped quote \", ne"sto3 , nes(ovo u" biti,( ne smije) proci"), "te,st"',
        ['nesto', 'nes"to2, neki escaped quote \", ne"sto3', 'nes(ovou" biti,( ne smije) proci")', '"te,st"'],
    ],
    [
        'nesto, nes"to2, neki escaped quote \", ne"sto3 , nes(ovo u" biti,( ne smije) proci"), "te,st",', // trailing comma
        ['nesto', 'nes"to2, neki escaped quote \", ne"sto3', 'nes(ovou" biti,( ne smije) proci")', '"te,st"'],
    ],
    [
        '"ovo bi trebalo ( proci", i ovo je drugo',
        ['"ovo bi trebalo ( proci"', 'iovojedrugo'],
    ],
    [
        '"ovo bi trebalo [ proci", i ovo je drugo',
        ['"ovo bi trebalo [ proci"', 'iovojedrugo'],
    ],
    [
        '"ovo bi trebalo { proci", i ovo je drugo',
        ['"ovo bi trebalo { proci"', 'iovojedrugo'],
    ],
    [
        '"ovo bi trebalo { proci", i ovo je drugo, {}',
        ['"ovo bi trebalo { proci"', 'iovojedrugo', '{}'],
    ],
];

/*foreach($tests as $ind => $test){
    $result = parse($test[0]);
    $pass = $result == $test[1];
    echo "test $ind: " . ($pass ? "passed" : "failed") . "\n";
    if(!$pass) print_r($result);
}*/

function parse(string $input, string $delimiter = ',', bool $omitDelimiter = true): array
{
    print("parse delimited by `$delimiter` called for: $input\n");

    $depthRound = 0;
    $depthBlock = 0;
    $depthCurly = 0;
    $inQuotes = false;

    $outputIndex = 0;

    for($i = 0; $i < strlen($input); $i++) {
        $write = true;
        $char = $input[$i];
        $charLeft = $i > 0 ? $input[$i - 1] : null;

        ($char === '"' && $charLeft !== '\\') and $inQuotes = !$inQuotes;

        if(!$inQuotes) {
            // skip whitespaces, tabs and newlines
            if (in_array($char, [' ', "\n", "\r", "\t"])) continue;

            ($char === ')' && $depthRound === 0) and throw new Exception('unexpected `)`');
            ($char === ']' && $depthBlock === 0) and throw new Exception('unexpected `]`');
            ($char === '}' && $depthCurly === 0) and throw new Exception('unexpected `}`');

            if ($depthRound === 0 && $depthBlock === 0 && $depthCurly === 0) {
                $match = true;

                for($j = 0; $j < strlen($delimiter); $j++)
                    if($input[$i + $j] !== $delimiter[$j]) {
                        $match = false;
                        break;
                    }

                if($match){
                    $outputIndex++;
                    $write = !$omitDelimiter;
                    if($omitDelimiter) $i += strlen($delimiter) - 1;
                }
            }

            $char === '(' and $depthRound++;
            $char === '[' and $depthBlock++;
            $char === '{' and $depthCurly++;
            $char === ')' and $depthRound--;
            $char === ']' and $depthBlock--;
            $char === '}' and $depthCurly--;
        }

        if($write) {
            $output[$outputIndex] ??= '';
            $output[$outputIndex] .= $char;
        }
    }

    if($inQuotes) throw new Exception("expected closing quote");
    if($depthRound > 0) throw new Exception("round bracket not closed");
    if($depthBlock > 0) throw new Exception("square bracket not closed");
    if($depthCurly > 0) throw new Exception("curly bracket not closed");

    return $output ?? [];
}

class PhpProxy {
    public function __get($name)
    {
        return $name(...);
    }
}

$test = fn($a) => print_r($a, true);

$vars = [
    'lang' => 'en',
    'nest' => [
        'one' => 'first',
        'two' => 'second',
        'three' => fn() => 'dynamic third',
    ],
    'dumb' => [
        'test1', 'test2', 'test3',
        'weird' => fn() => $test(...),
    ],
    'test' => fn() => 'dynamic',
    'add' => fn($a, $b) => $a + $b,
    'sub' => fn($a, $b) => $a - $b,
    'number' => 11,
    'concat' => fn(...$a) => implode('', $a),
    'implode' => fn($a) => print_r($a, true), //implode('', $a),
    'php' => new PhpProxy(),
    'bool' => true,
    'params' => [
        ' ', ['nesto', 'drugo', 'trece'],
    ]
];

function evaluate(string $input): mixed
{
    print("evaluate called for: $input\n");

    global $vars;

    $input = trim($input);

    if($input === 'null' || $input === '') return null;
    if($input === 'true') return true;
    if($input === 'false') return false;
    if(is_numeric($input)) return $input + 0;

    // all the str_contains() calls are for performance reasons
    // even though they might give false positives (e.g. operators in strings)

    // short ternary expression
    if(str_contains($input, '?:')) {
        $ternary = parse($input, '?:');
        if (count($ternary) > 1)
            foreach ($ternary as $index => $part) {
                $part = evaluate($part);
                if ($part || $index === count($ternary) - 1) return $part;
            }
    }

    // ternary expression
    if(str_contains($input, '?')) {
        $ternary = parse($input, '?');
        if (count($ternary) > 2) throw new Exception('unexpected `?`');
        if (count($ternary) === 2) {
            $values = parse($ternary[1], ':');
            if (count($values) > 2) throw new Exception('unexpected `:`');
            $values[1] ??= '""';

            return evaluate($ternary[0]) ? evaluate($values[0]) : evaluate($values[1]);
        }
    }

    // multiple AND expressions
    if(str_contains($input, '&')) {
        $and = parse($input, '&');
        if (count($and) > 1) {
            foreach ($and as $item) if (!evaluate($item)) return false;
            return true;
        }
    }

    // multiple OR expressions
    if(str_contains($input, '|')) {
        $or = parse($input, '|');
        if (count($or) > 1) {
            foreach ($or as $item) if (evaluate($item)) return true;
            return false;
        }
    }

    // comparison expressions
    if(str_contains($input, '===')) {
        $cmp = parse($input, '===');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) !== $first) return false;
            return true;
        }
    }
    if(str_contains($input, '!==')) {
        $cmp = parse($input, '!==');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) === $first) return false;
            return true;
        }
    }
    if(str_contains($input, '==')) {
        $cmp = parse($input, '==');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) != $first) return false;
            return true;
        }
    }
    if(str_contains($input, '!=')) {
        $cmp = parse($input, '!=');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) == $first) return false;
            return true;
        }
    }
    if(str_contains($input, '>=')) {
        $cmp = parse($input, '>=');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) < $first) return false;
            return true;
        }
    }
    if(str_contains($input, '<=')) {
        $cmp = parse($input, '<=');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) > $first) return false;
            return true;
        }
    }
    if(str_contains($input, '>')) {
        $cmp = parse($input, '>');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) <= $first) return false;
            return true;
        }
    }
    if(str_contains($input, '<')) {
        $cmp = parse($input, '<');
        if (count($cmp) > 1) {
            $first = evaluate(array_shift($cmp));
            foreach ($cmp as $item) if (evaluate($item) >= $first) return false;
            return true;
        }
    }

    // arithmetic expressions
    if(str_contains($input, '+')) {
        $add = parse($input, '+');
        if (count($add) > 1) {
            $result = 0;
            foreach ($add as $item) $result += evaluate($item);
            return $result;
        }
    }
    if(str_contains($input, '-')) {
        $sub = parse($input, '-');
        if (count($sub) > 1) {
            $result = evaluate(array_shift($sub));
            foreach ($sub as $item) $result -= evaluate($item);
            return $result;
        }
    }
    if(str_contains($input, '**')) {
        $pow = parse($input, '**');
        if (count($pow) > 1) {
            $result = evaluate(array_shift($pow));
            foreach ($pow as $item) $result **= evaluate($item);
            return $result;
        }
    }
    if(str_contains($input, '*')) {
        $mul = parse($input, '*');
        if (count($mul) > 1) {
            $result = 1;
            foreach ($mul as $item) $result *= evaluate($item);
            return $result;
        }
    }
    if(str_contains($input, '/')) {
        $div = parse($input, '/');
        if (count($div) > 1) {
            $result = evaluate(array_shift($div));
            foreach ($div as $item) $result /= evaluate($item);
            return $result;
        }
    }
    if(str_contains($input, '%')) {
        $mod = parse($input, '%');
        if (count($mod) > 1) {
            $result = evaluate(array_shift($mod));
            foreach ($mod as $item) $result %= evaluate($item);
            return $result;
        }
    }

    // string concatenation expression
    if(str_contains($input, '~')) {
        $concat = parse($input, '~');
        if (count($concat) > 1) {
            $result = '';
            foreach ($concat as $item) $result .= evaluate($item);
            return $result;
        }
    }

    // check if a string
    if(str_starts_with($input, '"')) return json_decode($input);

    // check if expression negated
    if(str_starts_with($input, '!'))
        return !evaluate(substr($input, 1));

    // if the expression is just wrapped in brackets, remove them
    if(str_starts_with($input, '(')){
        // check if the input ends with a trailing round bracket
        if(!str_ends_with($input, ')'))
            throw new Exception("expected closing round bracket");

        return evaluate(substr($input, 1, -1));
    }

    // check if the expression is an array
    if(str_starts_with($input, '[')) {
        // check if the input ends with a trailing block bracket and get rid of both brackets
        if(!str_ends_with($input, ']'))
            throw new Exception("expected closing block bracket");

        $input = substr($input, 1, -1);
        return evaluateList($input);
    }

    // check if the expression is a callable call
    // bracket will only be in expression if it is a callable call, all other cases are handled above
    if(str_contains($input, '(')) {
        // parse the input delimiting by opening bracket
        $parts = parse($input, '(', false);

        // parameter string is in last part, without trailing and leading brackets
        $params = substr(array_pop($parts), 1, -1);
        $before = implode('', $parts);

        // evaluate callable
        $callable = evaluate($before);
        if(!is_callable($callable)) throw new Exception("`$before` is not callable");

        // evaluate each parameter
        $params = evaluateList($params);

        return $callable(...$params);
    }

    // check if the expression is a nested variable
    if(str_contains($input, '.')) {
        $parts = parse($input, '.');
        $after = array_pop($parts);
        $before = implode('', $parts);

        $var = evaluate($before);
        return match(true){
            is_object($var) => $var->$after,
            is_array($var) && array_key_exists($after, $var) => $var[$after],
            default => throw new Exception("element `$after` not found in `$before`"),
        };
    }

    // finally, if expression isn't anything of above, it must be a variable
    if(!isset($vars[$input])) throw new Exception("unknown variable `$input`");
    return $vars[$input];
}

function evaluateList(string $params)
{
    $params = parse($params, ',');
    foreach($params as $param) {
        if(!str_starts_with($param, '...')) {
            $output[] = evaluate($param);
            continue;
        }

        $packed = evaluate(substr($param, 3));
        if(!is_array($packed)) throw new Exception("can't unpack `$param` - not an array");
        foreach($packed as $item) $output[] = $item;
    }

    return $output ?? [];
}

//var_dump(evaluate('php.print_r(["te(st", null, true], true)'));
//var_dump(parse('weird("te(st")', '(', false));

var_dump(evaluate('php.print_r([...params, "najs"], true)'));