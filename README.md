# Courier Fraud Checker BD for Laravel

A Laravel package to detect potential fraudulent orders by checking customer delivery behavior through Pathao, Steadfast, Redx, and CarryBee courier services in Bangladesh.

---

## ✨ Features

- Check customer delivery history across multiple couriers
- Validate Bangladeshi phone numbers
- Get success/cancel/total delivery statistics
- Supports Pathao, Steadfast, Redx, and CarryBee courier services

---

## ⚙️ Installation

1. Add the Repository in Your Laravel Project:

   ```bash
   "repositories": [
      {
         "type": "vcs",
         "url": "https://github.com/chonchol-alzaf/Courier-Fraud-Checker-BD"
      }
   ]

   ```

2. Install the package via Composer:

   ```bash
   composer require alzaf/courier-fraud-checker-bd
   ```

3. Publish the configuration file:

   ```bash
   php artisan vendor:publish --tag=config

   ```

### Add Service Provider (Laravel 5.4 and below)

In `config/app.php`:

```php
'providers' => [
    Alzaf\BdCourierBd\Providers\CourierFraudCheckerProvider::class,
],
```

## 🔧 Configuration

Add these environment variables to your `.env` file:

```env
# Enable/Disable Couriers
PATHAO_ENABLE=true
STEADFAST_ENABLE=true
REDX_ENABLE=false
CARRYBEE_ENABLE=false

# Pathao Credentials
PATHAO_USER=your_pathao_email
PATHAO_PASSWORD=your_pathao_password

# Steadfast Credentials
STEADFAST_USER=your_steadfast_email
STEADFAST_PASSWORD=your_steadfast_password

# Redx Credentials
REDX_PHONE=01XXXXXXXXX
REDX_PASSWORD=your_redx_password

# CarryBee Credentials
CARRYBEE_PHONE=01XXXXXXXXX
CARRYBEE_PASSWORD=your_carrybee_password
CARRYBEE_BUSINESS_ID=your_business_id
```

---

## 🚀 Usage

### Basic Usage

```php
use Alzaf\BdCourierBd\Facades\CourierFraudChecker;

$result = CourierFraudChecker::check('01641377742');

print_r($result);
```

**Output:**

```php
[
    'pathao' => ['success' => 5, 'cancel' => 2, 'total' => 7],
    'steadfast' => ['success' => 3, 'cancel' => 1, 'total' => 4]
]
```

---

## ☎️ Phone Number Validation

The package automatically validates phone numbers with this regex:

```php
/^01[3-9][0-9]{8}$/
```

✅ Valid examples:

- `01742263748`
- `01641377742`

❌ Invalid examples:

- `+8801742263748` (includes country code)
- `1209456790` (too short)
- `02171409567` (invalid prefix)

---

## 🛠️ Advanced Usage

### Using Individual Services

```php
use Alzaf\BdCourierBd\Services\PathaoService;
use Alzaf\BdCourierBd\Services\SteadfastService;

$pathao = (new PathaoService())->getCustomerDeliveryStats('01742263748');
$steadfast = (new SteadfastService())->getCustomerDeliveryStats('01742263748');
```

### Custom Validation Rules

```php
use Alzaf\BdCourierBd\Supports\CourierFraudCheckerHelper;

CourierFraudCheckerHelper::validatePhoneNumber('01742263748');
```

---

## 🧹 Troubleshooting

### Common Issues

1. **Missing Environment Variables**
   - Ensure all required credentials are set in `.env`
   - Run `php artisan config:clear` after updating

2. **Invalid Phone Number Format**
   - Must use local (BD) format like `01742263748`
   - Do **not** include `+88` prefix

---

## 📝 License

This package is open-source software licensed under the [GNU General Public License v3.0 only (GPL-3.0-only)](https://opensource.org/license/gpl-3-0/).

Under this license:

✅ **You are allowed to:**

- Use the package for personal or commercial projects.
- Modify the source code for your own use.
- Distribute the modified or original source code **provided** you also license it under **GPL-3.0-only**.
- Study and learn from the source code freely.

❌ **You are NOT allowed to:**

- Re-license the package under a different license.
- Distribute the package as part of a proprietary/commercial closed-source software without making your source code public.
- Sub-license or sell the software under a restrictive license.

**Important:**  
If you distribute modified versions of this package, you must also release your changes under the GPL-3.0-only license and include the original copyright.

> GPL-3.0-only promotes **freedom** to use, share, and modify, but ensures that any distributed version remains **free and open-source**.

---
