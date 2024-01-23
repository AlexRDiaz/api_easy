<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UpUser
 * 
 * @property int $id
 * @property string|null $username
 * @property string|null $email
 * @property string|null $provider
 * @property string|null $password
 * @property string|null $reset_password_token
 * @property string|null $confirmation_token
 * @property bool|null $confirmed
 * @property bool|null $blocked
 * @property string|null $fecha_alta
 * @property string|null $persona_cargo
 * @property string|null $estado
 * @property string|null $codigo_generado
 * @property string|null $permisos
 * @property string|null $telefono_1
 * @property string|null $telefono_2
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|OrdenesRetirosUsersPermissionsUserLink[] $ordenes_retiros_users_permissions_user_links
 * @property Collection|TransportadorasUsersPermissionsUserLink[] $transportadoras_users_permissions_user_links
 * @property Collection|Operadore[] $operadores
 * @property Collection|UpUsersPedidosShopifiesLink[] $up_users_pedidos_shopifies_links
 * @property Collection|UpUsersRoleLink[] $up_users_role_links
 * @property Collection|RolesFront[] $roles_fronts
 * @property Collection|Vendedore[] $vendedores
 *
 * @package App\Models
 */

use Illuminate\Contracts\Auth\Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;


class UpUser extends Model implements Authenticatable, JWTSubject
{
	function getJWTIdentifier()
	{
		return $this->getKey();
	}
	function getJWTCustomClaims()
	{
		return [];
	}


	// Resto de la implementación de tu modelo
	// ...

	// Implementación de métodos requeridos por Authenticatable
	public function getAuthIdentifierName()
	{
		return 'id'; // El nombre del campo de identificación en tu tabla
	}

	public function getAuthIdentifier()
	{
		return null; // Devuelve el valor de la clave primaria (por defecto, 'id')
	}

	public function getAuthPassword()
	{
		return $this->password; // Devuelve el campo de contraseña de tu modelo
	}

	public function getRememberToken()
	{
		return null; // Devuelve el campo de token de recuerdo (si lo tienes)
	}

	public function setRememberToken($value)
	{
		// Establece el campo de token de recuerdo (si lo tienes)
	}

	public function getRememberTokenName()
	{
		return 'remember_token'; // El nombre del campo de token de recuerdo (si lo tienes)
	}








	protected $table = 'up_users';

	protected $casts = [
		'confirmed' => 'bool',
		'blocked' => 'bool',
		'created_by_id' => 'int',
		'updated_by_id' => 'int',
		'accepted_terms_conditions' => 'bool'
	];

	protected $hidden = [
		'password',
		'reset_password_token',
		'confirmation_token'
	];

	protected $fillable = [
		'username',
		'email',
		'provider',
		'password',
		'reset_password_token',
		'confirmation_token',
		'confirmed',
		'blocked',
		'fecha_alta',
		'persona_cargo',
		'estado',
		'codigo_generado',
		'permisos',
		'payment_information',
		'telefono_1',
		'telefono_2',
		'created_by_id',
		'updated_by_id',
		'accepted_terms_conditions',
		'webhook_autome',
		'enable_autome',
		'config_autome'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function ordenes_retiros_users_permissions_user_links()
	{
		return $this->hasMany(OrdenesRetirosUsersPermissionsUserLink::class, 'user_id');
	}

	public function transportadoras_users_permissions_user_links()
	{
		return $this->hasMany(TransportadorasUsersPermissionsUserLink::class, 'user_id');
	}

	public function operadores()
	{
		return $this->belongsToMany(Operadore::class, 'up_users_operadore_links', 'user_id')
			->withPivot('id');
	}

	public function up_users_pedidos_shopifies_links()
	{
		return $this->hasMany(UpUsersPedidosShopifiesLink::class, 'user_id');
	}

	public function up_users_role_links()
	{
		return $this->hasMany(UpUsersRoleLink::class, 'user_id');
	}

	public function roles_fronts()
	{
		return $this->belongsToMany(RolesFront::class, 'up_users_roles_front_links', 'user_id')
			->withPivot('id', 'user_order');
	}

	public function vendedores()
	{
		return $this->belongsToMany(Vendedore::class, 'up_users_vendedores_links', 'user_id', 'vendedor_id')
			->withPivot('id', 'vendedor_order', 'user_order');
	}
	public function providers()
	{
		return $this->belongsToMany(Provider::class, 'up_users_providers_links', 'up_user_id', 'provider_id')
			->withPivot('id', 'provider_order', 'up_user_order');
	}
	public function transportadora()
	{
		return $this->hasManyThrough(Transportadora::class, TransportadorasUsersPermissionsUserLink::class, 'user_id', 'id', 'id', 'transportadora_id');
	}


	public function upUsersPedidos()
	{

		return $this->hasMany(PedidosShopify::class, 'id_comercial');
	}
	public function pedidosRutas()
	{
		return $this->hasManyThrough(
			PedidosShopify::class,
			// Modelo final que se quiere alcanzar
			PedidosShopifiesRutaLink::class,
			// Modelo intermedio
			'id_comercial',
			// Clave foránea en el modelo intermedio
			'ruta_id',
			// Clave foránea en el modelo final
			'id',
			// Clave local en el modelo de origen (upuser)
		);
	}
	public function pedidosTransportadoras()
	{
		return $this->hasManyThrough(
			PedidosShopify::class,
			// Modelo final que se quiere alcanzar
			PedidosShopifiesTransportadoraLink::class,
			// Modelo intermedio
			'id_comercial',
			// Clave foránea en el modelo intermedio
			'transportadora_id',
			// Clave foránea en el modelo final
			'id',
			// Clave local en el modelo de origen (upuser)
		);
	}

	public function rolesFronts()
	{
		return $this->belongsToMany(RolesFront::class, 'up_users_roles_front_links', 'user_id')
			->withPivot('id', 'user_order');
	}
}
