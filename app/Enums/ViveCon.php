<?php 
namespace App\Enums;

enum ViveCon: string
{
    case AMBOS = 'Ambos Padres';
    case MADRE = 'Madre';
    case PADRE = 'Padre';
    case ACUDIENTE = 'Acudiente';
}