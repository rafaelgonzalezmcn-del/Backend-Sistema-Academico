<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Tarea;
use App\Models\Modulo;
use App\Models\Parcial;
use App\Models\Parametro;
use App\Models\Entrega;
use App\Models\Material;
use App\Models\Subject;
use App\Models\ClassSchedule;
use App\Models\User;
use App\Models\Section;
use App\Models\SchoolYear;
use App\Models\Grade;
use App\Policies\TareaPolicy;
use App\Policies\ModuloPolicy;
use App\Policies\ParcialPolicy;
use App\Policies\ParametroPolicy;
use App\Policies\EntregaPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\ClassSchedulePolicy;
use App\Policies\UserPolicy;
use App\Policies\SectionPolicy;
use App\Policies\SchoolYearPolicy;
use App\Policies\GradePolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar policies explícitamente
        Gate::policy(Tarea::class, TareaPolicy::class);
        Gate::policy(Modulo::class, ModuloPolicy::class);
        Gate::policy(Parcial::class, ParcialPolicy::class);
        Gate::policy(Parametro::class, ParametroPolicy::class);
        Gate::policy(Entrega::class, EntregaPolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(ClassSchedule::class, ClassSchedulePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Section::class, SectionPolicy::class);
        Gate::policy(SchoolYear::class, SchoolYearPolicy::class);
        Gate::policy(Grade::class, GradePolicy::class);
    }
}
