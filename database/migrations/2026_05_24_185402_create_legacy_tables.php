<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========== CORE TABLES ==========
        
        // personal_info
        if (!Schema::hasTable('personal_info')) {
            Schema::create('personal_info', function (Blueprint $table) {
                $table->increments('biodataID');
                $table->string('session_id', 50)->nullable();
                $table->string('firstName', 100);
                $table->string('surname', 100);
                $table->string('middleName', 100)->nullable();
                $table->string('referenceNo', 50)->nullable();
                $table->string('passportPhotograph', 150);
                $table->string('title', 20);
                $table->string('maidenName', 100)->nullable();
                $table->string('dateOfBirth', 20);
                $table->string('placeOfBirth', 100);
                $table->string('birthCertificate', 150);
                $table->enum('gender', ['Male', 'Female']);
                $table->string('state_of_origin', 100);
                $table->string('local_govt', 100);
                $table->string('nationality', 100);
                $table->string('nin', 11);
                $table->string('phoneNo', 11);
                $table->string('email', 100);
                $table->string('preferrd_offc_loc', 100)->nullable();
                $table->integer('disability_id');
                $table->enum('disability', ['Yes', 'No'])->default('No');
                $table->integer('emailSent')->default(0);
                $table->datetime('dateCreated')->nullable();
                $table->datetime('updatedDate')->nullable();
                $table->index('referenceNo');
                $table->index('nin');
                $table->index('phoneNo');
                $table->index('email');
            });
        }
        
        // permanent_address
        if (!Schema::hasTable('permanent_address')) {
            Schema::create('permanent_address', function (Blueprint $table) {
                $table->increments('addressID');
                $table->integer('biodataID')->nullable();
                $table->string('street', 150);
                $table->string('house_no', 20);
                $table->string('area', 100);
                $table->string('city', 100);
                $table->integer('state');
                $table->string('country', 100);
                $table->string('phone', 16)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('created_at', 45)->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // residential_address
        if (!Schema::hasTable('residential_address')) {
            Schema::create('residential_address', function (Blueprint $table) {
                $table->increments('addressID');
                $table->integer('biodataID');
                $table->string('street', 150);
                $table->string('house_no', 100);
                $table->string('area', 100);
                $table->string('city', 100);
                $table->integer('state');
                $table->string('country', 100);
                $table->string('phone', 16)->nullable();
                $table->string('email', 100)->nullable();
                $table->datetime('created_at');
                $table->timestamp('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // next_of_kin
        if (!Schema::hasTable('next_of_kin')) {
            Schema::create('next_of_kin', function (Blueprint $table) {
                $table->increments('kinID');
                $table->integer('biodataID');
                $table->string('nameOfKin', 100);
                $table->string('addressofkin', 200);
                $table->string('relationshipKin', 50);
                $table->string('phoneOfKin', 16);
                $table->string('emailOfKin', 100);
                $table->enum('genderofkin', ['Male', 'Female']);
                $table->datetime('created_at');
                $table->timestamp('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // nysc
        if (!Schema::hasTable('nysc')) {
            Schema::create('nysc', function (Blueprint $table) {
                $table->increments('nyscID');
                $table->integer('biodataID');
                $table->date('nysc_year');
                $table->string('certificate_num', 45);
                $table->string('nysc_type', 45);
                $table->string('preferrd_offc_loc', 45)->nullable();
                $table->datetime('created_at');
                $table->datetime('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // prfssnalqualifictn
        if (!Schema::hasTable('prfssnalqualifictn')) {
            Schema::create('prfssnalqualifictn', function (Blueprint $table) {
                $table->increments('id');
                $table->string('biodataID', 11);
                $table->string('name_of_qualfctn', 50);
                $table->string('name_of_orgnztn', 50);
                $table->date('qualfctn_date');
                $table->string('membership_no', 50);
                $table->string('class_of_membrship', 50);
                $table->index('biodataID');
            });
        }
        
        // prisec_education
        if (!Schema::hasTable('prisec_education')) {
            Schema::create('prisec_education', function (Blueprint $table) {
                $table->increments('educationID');
                $table->integer('biodataID');
                $table->string('primarysch_Name', 150)->nullable();
                $table->string('primarysch_address', 200)->nullable();
                $table->string('primarysch_state', 100)->nullable();
                $table->string('primarysch_country', 100)->nullable();
                $table->string('primarysch_enddate', 50)->nullable();
                $table->string('secondrysch_name', 150)->nullable();
                $table->string('secondrysch_adress', 200)->nullable();
                $table->string('secondrysch_state', 100)->nullable();
                $table->string('secondrysch_country', 100)->nullable();
                $table->string('secondrysch_enddate', 50)->nullable();
                $table->datetime('created_at');
                $table->timestamp('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // university_education
        if (!Schema::hasTable('university_education')) {
            Schema::create('university_education', function (Blueprint $table) {
                $table->increments('educationID');
                $table->integer('biodataID');
                $table->string('institutionName', 150);
                $table->string('address', 200);
                $table->string('university_state', 100);
                $table->string('university_country', 100)->nullable();
                $table->string('discipline', 100);
                $table->string('degree_type', 100);
                $table->string('class_of_degree', 100);
                $table->integer('yearof_graduation');
                $table->string('matricNo', 50);
                $table->datetime('created_at');
                $table->datetime('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // masters_education
        if (!Schema::hasTable('masters_education')) {
            Schema::create('masters_education', function (Blueprint $table) {
                $table->increments('educationID');
                $table->integer('biodataID');
                $table->string('institutionName', 150)->nullable();
                $table->string('address', 200)->nullable();
                //$table->integer('masters_state')->nullable();
                $table->string('masters_state', 100)->nullable();
                $table->string('masters_country', 100)->nullable();
                $table->integer('yearof_graduation')->nullable();
                $table->string('discipline', 100)->nullable();
                $table->string('matricNo', 50)->nullable();
                $table->datetime('created_at');
                $table->datetime('updated_at')->nullable();
                $table->index('biodataID');
            });
        }
        
        // work_experience
        if (!Schema::hasTable('work_experience')) {
            Schema::create('work_experience', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('biodataID')->default(0);
                $table->string('position', 100);
                $table->string('company', 100);
                $table->date('startDate');
                $table->date('endDate');
                $table->index('biodataID');
            });
        }
        
        // user_phonenumber (enlisted numbers)
        if (!Schema::hasTable('user_phonenumber')) {
            Schema::create('user_phonenumber', function (Blueprint $table) {
                $table->increments('iduser_phonenumber');
                $table->string('users_phonenumber', 225)->unique();
            });
        }
        
        // recruitment_state
        if (!Schema::hasTable('recruitment_state')) {
            Schema::create('recruitment_state', function (Blueprint $table) {
                $table->integer('State_id')->primary();
                $table->string('StateName', 45)->nullable();
                $table->string('StateCode', 45)->nullable();
            });
        }
        
        // recruitment_local_gov
        if (!Schema::hasTable('recruitment_local_gov')) {
            Schema::create('recruitment_local_gov', function (Blueprint $table) {
                $table->integer('loclGov_id')->primary();
                $table->string('LocalGovName', 45)->nullable();
                $table->string('LocalGovCode', 45)->nullable();
            });
        }
        
        // disability
        if (!Schema::hasTable('disability')) {
            Schema::create('disability', function (Blueprint $table) {
                $table->increments('disability_id');
                $table->string('name', 45)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Drop in reverse order
        Schema::dropIfExists('work_experience');
        Schema::dropIfExists('masters_education');
        Schema::dropIfExists('university_education');
        Schema::dropIfExists('prisec_education');
        Schema::dropIfExists('prfssnalqualifictn');
        Schema::dropIfExists('nysc');
        Schema::dropIfExists('next_of_kin');
        Schema::dropIfExists('residential_address');
        Schema::dropIfExists('permanent_address');
        Schema::dropIfExists('personal_info');
        Schema::dropIfExists('user_phonenumber');
        Schema::dropIfExists('recruitment_local_gov');
        Schema::dropIfExists('recruitment_state');
        Schema::dropIfExists('disability');
    }
};