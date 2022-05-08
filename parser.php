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

function parse(string $input, ?string $delimiter = ',', bool $omitDelimiter = true): array
{
    print("params delimited by $delimiter called for: $input\n");

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
                if($delimiter === null)
                    $outputIndex++;
                else if($char === $delimiter) {
                    $outputIndex++;
                    $write = !$omitDelimiter;
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
];

function evaluate(string $input): mixed
{
    print('evaluate called for: ' . $input . "\n");

    global $vars;

    $input = trim($input);

    if($input === 'null') return null;
    if($input === 'true') return true;
    if($input === 'false') return false;
    if(is_numeric($input)) return $input + 0;

    //$and = parse($input, '&');


    // check if a string
    if(str_starts_with($input, '"')) return json_decode($input);

    // check if negated
    if(str_starts_with($input, '!'))
        return !evaluate(substr($input, 1));

    // if the expression is just wrapped in brackets, remove them
    if(str_starts_with($input, '(')){
        // check if the input ends with a trailing round bracket
        if(!str_ends_with($input, ']'))
            throw new Exception("expected closing round bracket");

        return evaluate(substr($input, 1, -1));
    }

    // check if the expression is an array
    if(str_starts_with($input, '[')) {
        // check if the input ends with a trailing block bracket and get rid of both brackets
        if(!str_ends_with($input, ']'))
            throw new Exception("expected closing block bracket");
        $input = substr($input, 1, -1);

        $array = parse($input);
        foreach($array as &$element)
            $element = evaluate($element);

        return $array;
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
        $params = parse($params);
        foreach($params as &$param)
            $param = evaluate($param);

        return $callable(...$params);
    }

    // check if the expression is a nested variable
    if(str_contains($input, '.')) {
        // get string before and after LAST dot
        $before = substr($input, 0, strrpos($input, '.'));
        $after = substr($input, strrpos($input, '.') + 1);

        $var = evaluate($before);

        return match(true){
            is_object($var) => $var->$after,
            is_array($var) && array_key_exists($after, $var) => $var[$after],
            default => throw new Exception("element `$after` in `$before` not found"),
        };
    }

    // finally, if expression isn't anything of above, it must be a variable
    if(!isset($vars[$input])) throw new Exception("unknown variable `$input`");
    return $vars[$input];
}

//var_dump(evaluate('php.print_r(["te(st", null, true], true)'));
//var_dump(parse('weird("te(st")', '(', false));

var_dump(parse('nesto & drugo |', '&'));