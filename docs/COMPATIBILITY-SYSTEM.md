# PC Compatibility System - Admin Guide

This document explains how to configure the PC Compatibility System introduced in version 2.0.0 of the W2F PC Configurator plugin.

## Overview

The compatibility system uses a structured approach to check if PC components work together:

1. **Facts** - Values extracted from product attributes (socket, wattage, dimensions, etc.)
2. **Calculators** - Compute derived values (required PSU wattage, max GPU length)
3. **Adjustments** - Modify derived values based on conditions (AIO radiator reduces GPU clearance)
4. **Policies** - Check compatibility rules (socket must match, GPU must fit)

## Admin Menu Pages

After activating the plugin, you'll find these pages under **PC Configurator**:

- **Component Types** - Map WooCommerce categories to component types
- **Attributes** - Define how product attributes map to facts
- **Calculators** - Configure power and clearance calculations
- **Policies** - Create compatibility rules
- **Adjustments** - Define conditional modifiers
- **Simulator** - Test configurations and see detailed traces

---

## 1. Component Type Mapping

**Location:** PC Configurator → Component Types

The system needs to know what type of PC component each product is. Map your WooCommerce product categories to these component types:

| Component Type | Description | Multi-Select |
|----------------|-------------|--------------|
| case | Computer cases | No |
| cpu | Processors | No |
| motherboard | Mainboards | No |
| gpu | Graphics cards | No |
| psu | Power supplies | No |
| cooler | CPU cooling solutions | No |
| ram | Memory modules | No |
| storage | SSDs and HDDs | Yes |
| fans | Case fans | Yes |
| accessories | Other accessories | Yes |

**Example:** Map your "Processors" and "AMD Processors" categories to the `cpu` component type.

---

## 2. Attribute Dictionary

**Location:** PC Configurator → Attributes

Define how WooCommerce product attributes translate to typed facts used by the compatibility system.

### Key Concepts

- **Internal Key:** Short name for the fact (e.g., `socket`)
- **Source Type:** Either `taxonomy` (pa_* attributes) or `meta` (custom fields)
- **Source Name:** The WooCommerce attribute taxonomy name (e.g., `pa_socket`)
- **Data Type:** How to parse the value:
  - `numeric` - Numbers (supports parsing "800W" → 800)
  - `enum` - Single value from a list
  - `set` - Multiple values (e.g., supported sockets)
  - `boolean` - Yes/No
  - `text` - Free text

### Pre-configured Attributes

The system comes with common PC attributes pre-configured:

| Attribute Key | Source | Type | Description |
|---------------|--------|------|-------------|
| cpu.socket | pa_socket | enum | CPU socket type |
| cpu.tdp_w | pa_tdp | numeric | CPU power consumption |
| motherboard.socket | pa_socket | enum | Motherboard socket |
| motherboard.form_factor | pa_form-factor | enum | ATX, mATX, ITX |
| motherboard.ram_type | pa_memory-type | enum | DDR4, DDR5 |
| gpu.power_w | pa_gpu-tdp | numeric | GPU power |
| gpu.length_mm | pa_gpu-length | numeric | GPU length |
| psu.wattage_w | pa_wattage | numeric | PSU output |
| case.max_gpu_length_mm | pa_max-gpu-length | numeric | Max GPU clearance |
| case.supported_radiators | pa_radiator-support | set | Supported case radiator sizes |
| cooler.supported_sockets | pa_cooler-sockets | set | Compatible sockets |
| cooler.radiator_size_mm | pa_radiator-size | numeric | AIO radiator size |

### Adding Custom Attributes

1. Go to **Attributes** page
2. Fill in the form:
   - Key: `component.attribute` format (e.g., `gpu.vram_gb`)
   - Internal Key: Short name (e.g., `vram_gb`)
   - Source: Select your WooCommerce attribute
   - Data Type: Choose appropriate type
   - Component Types: Select which components use this attribute
3. Click "Add Attribute"

---

## 3. Calculators

**Location:** PC Configurator → Calculators

Calculators compute derived values that policies use for comparisons.

### Required PSU Wattage Calculator

**Output:** `derived.required_psu_w`

**Formula:** `(sum of contributors + base_w) × headroom_factor`

**Parameters:**
- **Base System Power (W):** Power for motherboard, peripherals (default: 50W)
- **Headroom Factor:** Safety margin multiplier (default: 1.25 = 25% headroom)
- **Contributors:** Which component wattages to sum:
  - cpu.tdp_w (CPU TDP)
  - gpu.power_w (GPU power)
  - Others as configured

**Example:** CPU 125W + GPU 320W + Base 50W = 495W × 1.25 = 619W → Rounded to 650W

### Max GPU Length Calculator

**Output:** `derived.max_gpu_length_mm`

**Formula:** `case.max_gpu_length_mm - derived.gpu_clearance_penalty_mm`

**Parameters:**
- **Base Source:** Where to get case GPU clearance
- **Default Maximum:** Used if no case is selected (default: 400mm)
- **Penalty Key:** Where adjustments store penalties

---

## 4. Compatibility Policies

**Location:** PC Configurator → Policies

Policies check compatibility rules using simple templates. No coding required!

### Policy Types

#### MATCH
Checks if two enum values are equal.
- **Use for:** Socket compatibility, RAM type matching
- **Example:** CPU socket MUST equal motherboard socket

#### CONTAINS
Checks if a set contains a specific value.
- **Use for:** Form factor support, socket support
- **Example:** Case supported form factors MUST contain motherboard form factor

#### COMPARE
Compares two numeric values.
- **Use for:** Wattage checks, slot counts
- **Example:** PSU wattage MUST be ≥ required wattage

#### FIT
Checks if a component fits within a constraint.
- **Use for:** Physical dimensions
- **Example:** GPU length MUST be ≤ max GPU clearance

#### COUNT_LIMIT
Limits how many items of a type can be selected.
- **Use for:** Drive slots, RAM slots
- **Example:** NVMe drives MUST be ≤ motherboard M.2 slots

#### REQUIRES_IF
Requires a component when another is selected.
- **Use for:** Dependencies
- **Example:** If GPU is selected THEN PSU must be selected

#### WARNING_RANGE
Shows warning when value is near threshold.
- **Use for:** Soft limits, recommendations
- **Example:** Warn if PSU within 10% of required wattage

### Policy Settings

- **Priority:** Lower number = evaluated first (1-999)
- **Severity:** 
  - `Block` - Prevents purchase
  - `Warn` - Shows warning only
- **Message Template:** Use `{{fact.key}}` for variable substitution
  - Example: `GPU ({{gpu.length_mm}}mm) is too long for case (max {{derived.max_gpu_length_mm}}mm)`

### Pre-configured Policies

| Policy | Type | Description |
|--------|------|-------------|
| CPU Socket Compatibility | MATCH | CPU socket must match motherboard |
| RAM Type Compatibility | MATCH | RAM type must match motherboard |
| Case Form Factor Support | CONTAINS | Case must support motherboard form factor |
| Cooler Socket Support | CONTAINS | Cooler must support CPU socket |
| PSU Wattage Check | COMPARE | PSU wattage ≥ required |
| GPU Clearance Check | FIT | GPU must fit in case |
| NVMe Slot Limit | COUNT_LIMIT | NVMe drives ≤ M.2 slots |
| PSU Headroom Warning | WARNING_RANGE | Warn if PSU close to limit |

---

## 5. Adjustments

**Location:** PC Configurator → Adjustments

Adjustments modify calculator inputs based on conditions.

### Example: AIO Radiator Reduces GPU Clearance

When a 240mm+ AIO cooler is front-mounted, it takes space that would otherwise be available for the GPU.

**Conditions:**
- cooler.type = aio
- cooler.radiator_size_mm >= 240

**Effect:**
- Target: `derived.gpu_clearance_penalty_mm`
- Action: Add 40

**Result:** Max GPU length is reduced by 40mm

### Creating an Adjustment

1. Name your adjustment descriptively
2. Define conditions (AND or OR logic)
3. Specify:
   - **Target Key:** The derived value to modify
   - **Effect:** Add, Subtract, Multiply, or Set
   - **Delta:** The value to apply
4. Add a reason (shown in the trace)

### Pre-configured Adjustments

| Adjustment | Conditions | Effect |
|------------|------------|--------|
| 240mm AIO Front Mount | cooler.type=aio AND radiator≥240mm | -40mm GPU clearance |
| 360mm AIO Front Mount | cooler.type=aio AND radiator≥360mm | -55mm GPU clearance |

---

## 6. Simulator

**Location:** PC Configurator → Simulator

Test your compatibility configuration before going live.

### How to Use

1. Enter product IDs for each component type
2. Click "Run Simulation"
3. Review the results:
   - **Overall Status:** Compatible or Incompatible
   - **Errors/Warnings:** Specific issues
   - **Facts Extracted:** What values were read
   - **Adjustments Applied:** What modifications were made
   - **Calculator Results:** Derived values with breakdowns
   - **Policy Evaluations:** Pass/fail for each policy

### Understanding the Trace

The simulator shows exactly WHY something is incompatible:

```
Policy: GPU Clearance Check
Status: ✗ BLOCK
Compared: 350mm <= 340mm
Message: GPU (350mm) does not fit in case (max 340mm)
```

This tells you:
- The GPU is 350mm long
- The case only has 340mm clearance (after AIO penalty)
- The GPU needs to be 10mm shorter to fit

---

## Troubleshooting

### Products Showing as Incompatible Unexpectedly

1. Use the **Simulator** to test the configuration
2. Check if all required attributes are populated on products
3. Verify attribute sources match your WooCommerce setup
4. Check if an Adjustment is applying unexpected penalties

### Compatibility Not Working

1. Ensure the new engine is active (check for admin menu pages)
2. Verify Component Type mappings are set up
3. Check that policies are enabled
4. Test with the Simulator

### Missing Attributes

If products don't have the expected attributes:
1. Add the WooCommerce attribute taxonomy (e.g., `pa_socket`)
2. Assign values to your products
3. Map the attribute in the Attribute Dictionary

---

## Best Practices

1. **Start with defaults** - The pre-configured policies cover common PC compatibility
2. **Test incrementally** - Use the Simulator when adding new policies
3. **Use descriptive messages** - Help customers understand why items are incompatible
4. **Set appropriate severity** - Use "Warn" for soft limits, "Block" for hard incompatibilities
5. **Document your changes** - The system is transparent; use the trace to verify

---

## Technical Reference

### Fact Key Format

Facts use dot notation: `component.attribute`

Examples:
- `cpu.socket` - CPU socket type
- `gpu.length_mm` - GPU length in millimeters
- `derived.required_psu_w` - Calculated required PSU wattage

### Message Variables

Use `{{key}}` in messages to include fact values:

```
PSU ({{psu.wattage_w}}W) is below the recommended {{derived.required_psu_w}}W
```

### Comparison Operators

| Operator | Description |
|----------|-------------|
| `>=` | Greater than or equal |
| `>` | Greater than |
| `<=` | Less than or equal |
| `<` | Less than |
| `==` | Equal |
| `!=` | Not equal |

---

## Getting Help

For technical support or feature requests, contact your development team with:
- Screenshot of the Simulator results
- Specific products being tested
- Expected vs actual behavior
