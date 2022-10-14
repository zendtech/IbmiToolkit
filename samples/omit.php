<?php

/*
This code snippet shows how to specify an option(*OMIT) parameter that you can pass into an RPG program 
that uses an *OMIT keyword, as:
       D  omitparam                       10A   options(*OMIT)
*/

// specify 'omit' in the first argument (other valid values for $io being 'in', 'out', 'both'):
$params[] = $conn->AddParameterChar('omit', 10, 'Param to allow omitting', 'OMITPARAM', '');

