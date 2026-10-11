<?php

namespace PhpObfuscator;

interface ModuleInterface
{
    /**
     * Обработка одного токена.
     *
     * @param array{0: int, 1: string, 2: int}|string       $token    текущий токен
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens   полный список токенов
     * @param int                                           $position позиция текущего токена в списке
     *
     * @return string преобразованное представление токена
     */
    public function process(string|array $token, array $tokens, int $position): string;
}
