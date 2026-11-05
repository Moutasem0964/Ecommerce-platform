<?php

// Tinker (PsySH) config for this project.
// Run every tinker command in the same process instead of a forked child:
// the gRPC extension does not survive fork(), which made tinker hang or die
// when a gRPC client was used across commands.
return [
    'usePcntl' => false,
];
