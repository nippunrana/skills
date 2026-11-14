# DataViews & DataForm (WordPress 7.0+)

DataViews renders a dataset as a table, grid, list or activity feed with search, filters, sorting and pagination. DataForm is the matching form layer. Both are driven by **field definitions**.

**Load this file when**: the user is building or modifying a DataViews-driven admin screen, wants validation on a field, asks about `groupBy` / `onReset`, or needs `getValueFormatted`.

---

## 0. Getting the components

DataViews is not a core script handle — install and bundle it.

```
npm install @wordpress/dataviews --save
```

When the build uses `@wordpress/scripts`, import from the `/wp` entry so WordPress's own packages stay external:

```js
import { DataViews } from '@wordpress/dataviews/wp';
```

---

## 1. `groupBy` is an object

The old `groupByField: 'status'` string key was replaced in 7.0 by a `groupBy` object:

```js
const view = {
	type: 'table',
	groupBy: {
		field: 'status',   // required — the field id to group by
		direction: 'desc', // optional, 'asc' | 'desc'. Defaults to 'asc'.
		showLabel: true,   // optional boolean — label visibility for the groups
	},
	// …
};
```

If you find `groupByField` in existing code, migrate it to `groupBy: { field: … }`.

---

## 2. `onReset` semantics

The "Reset view" button is governed by the `onReset` prop:

| `onReset` value | Button | Use when… |
|---|---|---|
| `undefined` | Hidden | The view is not persisted, so there is nothing to reset. |
| `false` | Shown, disabled | The view is persisted and currently matches the default. |
| `function` | Shown, enabled | The view is persisted and has been modified. Called when the user clicks the button. |

```js
<DataViews
	data={ data }
	view={ view }
	onChangeView={ setView }
	onReset={ isModified ? resetToDefault : false }
/>
```

`isModified` and `resetToDefault` come from whatever persists the view in your screen.

---

## 3. Field validation: `isValid`

Validation rules live in the field's **`isValid`** object. A key named `validation` is ignored.

| Rule | Field types | Notes |
|---|---|---|
| `required` | any | Boolean. |
| `pattern` | `text`, `email`, `telephone`, `url` | Regex source string, no surrounding slashes. |
| `minLength` / `maxLength` | `text`, `email`, `telephone`, `url` | |
| `min` / `max` | `integer`, `number` | |
| `elements` | fields with `elements` | Restricts the value to the listed elements. On by default. |
| `custom` | any | `( item, field ) => string | null`. Return a message when invalid, `null` when valid. |

```js
const fields = [
	{
		id: 'order_id',
		label: 'Order ID',
		type: 'text',
		isValid: {
			required: true,
			pattern: '^ORD-[0-9]{6}$',
			minLength: 10,
			maxLength: 10,
		},
	},
	{
		id: 'quantity',
		label: 'Quantity',
		type: 'integer',
		isValid: { min: 1, max: 999 },
	},
];
```

Validation runs in the browser. Mirror every rule in the REST handler — client validation is UX, not security.

---

## 4. `getValueFormatted` for display

`getValueFormatted` receives **one object**, `{ item, field }`, and returns the string to display. The stored value is unchanged.

```js
const fileSizeField = {
	id: 'file_size',
	label: 'Size',
	type: 'integer',
	getValueFormatted: ( { item, field } ) => {
		const bytes = field.getValue( { item } );
		if ( bytes < 1024 ) {
			return `${ bytes } B`;
		}
		if ( bytes < 1024 * 1024 ) {
			return `${ ( bytes / 1024 ).toFixed( 1 ) } KB`;
		}
		return `${ ( bytes / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
	},
};
```

Each field type ships a default formatter (number separators, dates). Override it only when the stored and displayed values differ in a way the type does not handle. Keep sorting and filtering logic out of it — those work on the raw value.

---

## 5. Common pitfalls

| Symptom | Cause | Fix |
|---|---|---|
| Grouping ignored | Used `groupByField` or a string `groupBy` | Use the object form with `field` |
| "Reset view" button always hidden | `onReset` left `undefined` | Pass `false` or a function once the view is persisted |
| Validation rules ignored | Rules placed under `validation` | Put them under `isValid` |
| Formatter shows `undefined` | Treated the argument as the item | Destructure `( { item, field } )` |
| Validation passes but server rejects | Only client-side rules | Mirror the rules in the REST handler |
| Regex never matches | Wrapped the pattern in `/…/` | Pass the source string only |
