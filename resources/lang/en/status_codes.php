<?php

// Return parsed json file
return json_decode(file_get_contents(public_path() . '/json/ec5-status-codes/en.json'), true);