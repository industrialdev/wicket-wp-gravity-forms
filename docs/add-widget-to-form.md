---
title: "Add a Wicket Widget Field to a Form"
audience: end-user
---

# Add a Wicket Widget Field to a Form

Wicket widget fields embed a live Wicket editor — such as profile editing or communication preferences — directly inside a Gravity Form. This guide walks through adding one to any form.

## Before You Start

- Gravity Forms must be active
- Wicket Gravity Forms plugin must be installed and active
- The page should be behind a login wall (Wicket widgets require an authenticated user)

## Add a Widget Field

1. Open the Gravity Forms form editor for the form you want to edit.
2. In the **Wicket** field group (bottom of the field picker), drag a widget field onto your form — for example **Profile Widget** or **Preferences**.
3. Open the field's **General Settings** to configure it.

## Connect Org. Profile Widget to an Org. Search Field

If you are adding an **Org. Profile W.**, **Add. Info. W.**, or any field that needs an organization context:

1. Add an **Org. Search** field to your form **before** the widget field (on the same page, or an earlier page of a multi-page form).
2. When a user selects an organization in the Org. Search field, the widget field automatically receives that organization's UUID.

> Placing the Org. Search on the same page is recommended for best UX.

## Display Options

All widget fields have a **Hide Label** toggle. Enable it to suppress the field label if the widget renders its own heading.

## Save the Form

Click **Update Form**. The widget now appears on the frontend for any logged-in user who fills out the form.

## Field Types Available

| Widget Field | What it does |
|---|---|
| **Profile Widget** | Lets users edit their own name, email, and basic profile data |
| **Org. Profile W.** | Lets users edit their organization's profile (requires Org. Search) |
| **Add. Info. W.** | Lets users fill in custom additional information fields defined in Wicket |
| **Preferences** | Lets users update their communication preferences |
