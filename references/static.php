<?php
function getReference($boiler, $load) {
    // Пока возвращаем постоянный эталон, игнорируя нагрузку
    return $boiler['reference'];
}