<?php

const QUESTIONS_PER_QUIZ = 10;
const STARTING_LIVES = 3;

const DIFFICULTY_POINTS = [
    'easy' => 10,
    'medium' => 15,
    'hard' => 20,
];

function points_for_difficulty(string $difficulty): int
{
    return DIFFICULTY_POINTS[$difficulty] ?? 0;
}
