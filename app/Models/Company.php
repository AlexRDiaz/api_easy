<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Company
 * 
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $website
 * @property string|null $logo
 * @property string|null $timezone
 * @property bool|null $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|CompanyCountriesLink[] $company_countries_links
 * @property Collection|Provider[] $providers
 * @property Collection|Transportadora[] $transportadoras
 * @property Collection|UpUsersRolesFrontLink[] $up_users_roles_front_links
 * @property Collection|Vendedore[] $vendedores
 *
 * @package App\Models
 */
class Company extends Model
{
	protected $table = 'companies';

	protected $casts = [
		'active' => 'bool'
	];

	protected $fillable = [
		'name',
		'email',
		'phone',
		'address',
		'website',
		'logo',
		'timezone',
		'active'
	];

	public function company_countries_links()
	{
		return $this->hasMany(CompanyCountriesLink::class);
	}

	public function providers()
	{
		return $this->hasMany(Provider::class);
	}

	public function transportadoras()
	{
		return $this->hasMany(Transportadora::class);
	}

	public function up_users_roles_front_links()
	{
		return $this->hasMany(UpUsersRolesFrontLink::class);
	}

	public function vendedores()
	{
		return $this->hasMany(Vendedore::class);
	}
}
