<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AdminUser
 * 
 * @property int $id
 * @property string|null $firstname
 * @property string|null $lastname
 * @property string|null $username
 * @property string|null $email
 * @property string|null $password
 * @property string|null $reset_password_token
 * @property string|null $registration_token
 * @property bool|null $is_active
 * @property bool|null $blocked
 * @property string|null $prefered_language
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $created_by_id
 * @property int|null $updated_by_id
 * 
 * @property AdminUser|null $admin_user
 * @property Collection|AdminPermission[] $admin_permissions
 * @property Collection|AdminRole[] $admin_roles
 * @property Collection|AdminUser[] $admin_users
 * @property Collection|AdminUsersRolesLink[] $admin_users_roles_links
 * @property Collection|File[] $files
 * @property Collection|GenerateCode[] $generate_codes
 * @property Collection|GenerateReport[] $generate_reports
 * @property Collection|I18nLocale[] $i18n_locales
 * @property Collection|IngresosEgreso[] $ingresos_egresos
 * @property Collection|MiSaldo[] $mi_saldos
 * @property Collection|Novedade[] $novedades
 * @property Collection|Operadore[] $operadores
 * @property Collection|OrdenesRetiro[] $ordenes_retiros
 * @property Collection|PedidoFecha[] $pedido_fechas
 * @property Collection|PedidosShopify[] $pedidos_shopifies
 * @property Collection|ProductoShopify[] $producto_shopifies
 * @property Collection|RolesFront[] $roles_fronts
 * @property Collection|Ruta[] $rutas
 * @property Collection|SaldoL[] $saldo_ls
 * @property Collection|Statistic[] $statistics
 * @property Collection|StrapiApiTokenPermission[] $strapi_api_token_permissions
 * @property Collection|StrapiApiToken[] $strapi_api_tokens
 * @property Collection|StrapiTransferTokenPermission[] $strapi_transfer_token_permissions
 * @property Collection|StrapiTransferToken[] $strapi_transfer_tokens
 * @property Collection|SubRuta[] $sub_rutas
 * @property Collection|Transportadora[] $transportadoras
 * @property Collection|UpPermission[] $up_permissions
 * @property Collection|UpRole[] $up_roles
 * @property Collection|UpUser[] $up_users
 * @property Collection|UploadFolder[] $upload_folders
 * @property Collection|Vendedore[] $vendedores
 *
 * @package App\Models
 */
class AdminUser extends Model
{
	protected $table = 'admin_users';

	protected $casts = [
		'is_active' => 'bool',
		'blocked' => 'bool',
		'created_by_id' => 'int',
		'updated_by_id' => 'int'
	];

	protected $hidden = [
		'password',
		'reset_password_token',
		'registration_token'
	];

	protected $fillable = [
		'firstname',
		'lastname',
		'username',
		'email',
		'password',
		'reset_password_token',
		'registration_token',
		'is_active',
		'blocked',
		'prefered_language',
		'created_by_id',
		'updated_by_id'
	];

	public function admin_user()
	{
		return $this->belongsTo(AdminUser::class, 'updated_by_id');
	}

	public function admin_permissions()
	{
		return $this->hasMany(AdminPermission::class, 'updated_by_id');
	}

	public function admin_roles()
	{
		return $this->hasMany(AdminRole::class, 'updated_by_id');
	}

	public function admin_users()
	{
		return $this->hasMany(AdminUser::class, 'updated_by_id');
	}

	public function admin_users_roles_links()
	{
		return $this->hasMany(AdminUsersRolesLink::class, 'user_id');
	}

	public function files()
	{
		return $this->hasMany(File::class, 'updated_by_id');
	}

	public function generate_codes()
	{
		return $this->hasMany(GenerateCode::class, 'updated_by_id');
	}

	public function generate_reports()
	{
		return $this->hasMany(GenerateReport::class, 'updated_by_id');
	}

	public function i18n_locales()
	{
		return $this->hasMany(I18nLocale::class, 'updated_by_id');
	}

	public function ingresos_egresos()
	{
		return $this->hasMany(IngresosEgreso::class, 'updated_by_id');
	}

	public function mi_saldos()
	{
		return $this->hasMany(MiSaldo::class, 'updated_by_id');
	}

	public function novedades()
	{
		return $this->hasMany(Novedade::class, 'updated_by_id');
	}

	public function operadores()
	{
		return $this->hasMany(Operadore::class, 'updated_by_id');
	}

	public function ordenes_retiros()
	{
		return $this->hasMany(OrdenesRetiro::class, 'updated_by_id');
	}

	public function pedido_fechas()
	{
		return $this->hasMany(PedidoFecha::class, 'updated_by_id');
	}

	public function pedidos_shopifies()
	{
		return $this->hasMany(PedidosShopify::class, 'updated_by_id');
	}

	public function producto_shopifies()
	{
		return $this->hasMany(ProductoShopify::class, 'updated_by_id');
	}

	public function roles_fronts()
	{
		return $this->hasMany(RolesFront::class, 'updated_by_id');
	}

	public function rutas()
	{
		return $this->hasMany(Ruta::class, 'updated_by_id');
	}

	public function saldo_ls()
	{
		return $this->hasMany(SaldoL::class, 'updated_by_id');
	}

	public function statistics()
	{
		return $this->hasMany(Statistic::class, 'updated_by_id');
	}

	public function strapi_api_token_permissions()
	{
		return $this->hasMany(StrapiApiTokenPermission::class, 'updated_by_id');
	}

	public function strapi_api_tokens()
	{
		return $this->hasMany(StrapiApiToken::class, 'updated_by_id');
	}

	public function strapi_transfer_token_permissions()
	{
		return $this->hasMany(StrapiTransferTokenPermission::class, 'updated_by_id');
	}

	public function strapi_transfer_tokens()
	{
		return $this->hasMany(StrapiTransferToken::class, 'updated_by_id');
	}

	public function sub_rutas()
	{
		return $this->hasMany(SubRuta::class, 'updated_by_id');
	}

	public function transportadoras()
	{
		return $this->hasMany(Transportadora::class, 'updated_by_id');
	}

	public function up_permissions()
	{
		return $this->hasMany(UpPermission::class, 'updated_by_id');
	}

	public function up_roles()
	{
		return $this->hasMany(UpRole::class, 'updated_by_id');
	}

	public function up_users()
	{
		return $this->hasMany(UpUser::class, 'updated_by_id');
	}

	public function upload_folders()
	{
		return $this->hasMany(UploadFolder::class, 'updated_by_id');
	}

	public function vendedores()
	{
		return $this->hasMany(Vendedore::class, 'updated_by_id');
	}
}
