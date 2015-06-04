<?php

print '<pre>';

$iterations = 1000000;

class Controller {
    public function action() {

    }
}

$object  = new Controller();
$context = new stdClass();
$method_name = 'action';


print '$object->action() <br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i += 1) {
    $object->action();
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print '$object->$method_name() <br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i += 1) {
    $object->$method_name();
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'call_user_func() <br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    call_user_func(array($object, $method_name));
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'call_user_func_array() <br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    call_user_func_array(array($object, $method_name), [1,2,3,4,5]);
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'function () {} <br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $f = function () {};
    $f();
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'function () {} | bindTo <br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $f = function () {};
    $f->bindTo($context, $context);
    $f();
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'ReflectionMethod | invoke()<br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    (new ReflectionMethod($object, $method_name))->invoke($object);
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'ReflectionMethod | invokeArgs()<br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    (new ReflectionMethod($object, $method_name))->invokeArgs($object, [1,2,3,4,5]);
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'ReflectionMethod | getClosure()<br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $method = (new ReflectionMethod($object, $method_name))->getClosure($object);
    $method();
}
print '<b>' . (microtime(true) - $start) . '</b>';


print '<br /><br />';
print 'ReflectionMethod | getClosure() | bindTo<br />';
$start = microtime(true);
for ($i = 0; $i < $iterations; $i++) {
    $method = (new ReflectionMethod($object, $method_name))->getClosure($object)->bindTo($context, $context);
    $method();
}
print '<b>' . (microtime(true) - $start) . '</b>';





// Stupid check, but... :) 
// turns out $_SERVER is almost 3 times faster.

// print '<br /><br />';
// print '$_SERVER["REQUEST_METHOD"] === "GET"<br />';
// $start = microtime(true);
// for ($i = 0; $i < $iterations; $i++) {
//     if ($_SERVER['REQUEST_METHOD'] === 'GET') {

//     }
// }
// print '<b>' . (microtime(true) - $start) . '</b>';


// print '<br /><br />';
// print 'getenv("REQUEST_METHOD") === "GET"<br />';
// $start = microtime(true);
// for ($i = 0; $i < $iterations; $i++) {
//     if (getenv('REQUEST_METHOD') === 'GET') {

//     }
// }
// print '<b>' . (microtime(true) - $start) . '</b>';