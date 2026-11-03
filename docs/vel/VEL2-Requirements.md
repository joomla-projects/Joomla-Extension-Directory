# Concept for the New Joomla Vulnerable Extension List (VEL)

## 1. Purpose of the VEL

The Joomla Vulnerable Extension List (VEL) is intended to be a central security information service for the Joomla ecosystem.

Its goals are:

* to record and maintain known security issues in Joomla extensions,
* to inform the Joomla community about these issues in a consistent and timely way,
* to help web administrators identify risks in their own installations and keep their sites up to date,
* to serve as a central intake point for potential issue reporters, including researchers, extension developers, and end users.

The VEL should not only be a publication channel, but also a trusted coordination and communication layer for extension-related security issues.

---

## 2. Core Components of the VEL

### 2.1 Ticket System for Communication

The VEL should include a ticket-based communication system for handling inbound reports and coordination with stakeholders.

Requirements:

* Reporters, extension developers, and users must be able to contact the VEL team by email and via a web form.
* The ticket system should support:

  * intake of vulnerability reports,
  * internal triage and assignment,
  * secure communication with reporters and developers,
  * status tracking throughout the review and publication process.

This component should focus on workflow efficiency and reliability.

### 2.2 Custom Joomla 6.x Component for Issue Management and Publication

A dedicated Joomla 6.x component should be developed to manage, display, and export VEL entries.

---

## 3. Backend Administration UI

The backend administration interface should be designed from a management perspective: as simple as possible for editors, while still capturing everything needed to generate the CVE JSON output reliably.

The component should keep the input model lean and derive the final JSON structure from the stored data.

### 3.1 Field Inventory and Data Types

#### A. General advisory fields

* `title` — **string**
* `state` — **enum**
  Example values: `draft`, `ready`, `published`, `retired`

These are either fixed by the component or generated automatically during JSON export.

#### B. Publication metadata

The following dates must be generated automatically by the component:

* `dateReserved` — **datetime**
* `datePublished` — **datetime**
* `dateUpdated` — **datetime**

Editors should not enter these manually.

#### C. Advisory description

A single description field is sufficient:

* `description` — **rich text / HTML string**

#### D. Problem classification

The backend must support one or more CWE entries.

For each problem classification entry:

* `cweId` — **string**
* `description` — **string**

This is stored as a **1-n tuple list** of CWE ID and description pairs.

#### E. Affected product fields

The backend must support multiple affected products per advisory.

For each affected product entry:

* `vendor` — **string**
* `product` — **string**
* `versions[]` — **array of version range entries**

For each version range entry:

* `from` — **string**
* `to` — **string**

Only affected version ranges need to be stored.

The component must support advisories that affect multiple products from the same vendor.

#### F. Joomla extension identification fields

Vulnerable extensions might not exist in the JED extension database. Ideally they do and selecting from a searchable Extension list would allow the JED Id of the extension to be stored. The form should allow the ability to enter details about the extension where it cannot be found in the JED. 
So we will need, Extension Title, Extension Version, Supplier, SupplierWebsiteURL or JED ID.


#### G. Reference fields

* `references[]` — **array of objects**

For each entry in `references[]`:

* `url` — **URL string**
* `tags[]` — **array of strings**

#### H. Reporter / credit fields

The backend must allow storing one or more reporters for credit and attribution.

For each reporter:

* `name` — **string**
* `role` — **string / enum, optional**

This should support 1-n reporter entries.

### 3.2 Receiving Data Feeds

#### 3.2.1 National Vulnerability Database (https://nvd.nist.gov/)

The backend should have a method to check the NVD for Joomla Extension advisories and to create entries into the VEL system for these items.  This needs to be planned and researched and documented as well as coded.

#### 3.2.1 CNA Feed

https://www.cve.org/PartnerInformation/ListofPartners/partner/Joomla contains reported vulnerabilities within the Joomla codebase / extensions. We need a feed from this source to create and update the VEL system. This needs to be planned and researched and documented as well as coded.

---

## 4. Frontend Functionality

### 4.1 Filterable List View

The public frontend should provide a filterable HTML list view for users who want to check known vulnerabilities for each type of vulnerable entry, those that are Live, those Patched and those Abandoned.

Required filters:

* full-text search across:
  * title
  * description
  * vendor
  * product
* minimum CVSS score
* maximum CVSS score
* severity

Default sorting:

* descending by publication date

The list view must show, at list level, the following fields:

* one or more product names
* vendor
* CVSS score
* severity
* publication date
* title

### 4.2 Detail View

Each vulnerability entry must also have a detailed view containing all available information, including:

* full advisory text,
* affected products,
* version ranges,
* problem type and vulnerability classification,
* references,
* reporters / credits,
* publication metadata,
* and all other structured fields stored for the record.

### 4.3 “Generate CVE JSON” Function

The backend should offer a function called **Generate CVE JSON**.

Purpose:

* to create a draft JSON template in the required format,
* to speed up CVE assignment,
* and to reduce manual formatting work when preparing an advisory for publication.

This function should generate the JSON structure from the simplified internal management data model.

### 4.4 RSS Output

The VEL must provide an RSS feed of published vulnerabilities.

This allows interested users, researchers, and administrators to subscribe to updates without needing to check the website manually.

Current feed for reference is available from: https://extensions.joomla.org/vulnerable-extensions/json-feed/

### 4.5 Filterable JSON Output

The VEL should also expose a filterable JSON endpoint.

A key requirement is the ability to pass one or more extension identifiers.

Use case:

* A Joomla site can query the VEL for known vulnerabilities affecting installed extensions.
* The results can then be shown in the Joomla backend to alert site administrators.

This JSON output should support automation and integration into external systems.

---

## 5. Data Model Considerations

### 5.1 Multi-Product Advisories

A single advisory may cover multiple products from the same vendor. The data model and the user interface must fully support this.

Example:

* one vendor,
* one advisory,
* multiple affected products,
* multiple version ranges,
* one shared description,
* one or more CWE classifications,
* one or more reporters / credits.

This means the VEL must not assume a one-advisory-to-one-product relationship.

### 5.2 Representation in Input and Output

The schema should preserve:

* the shared advisory metadata,
* each affected product as a separate affected item,
* version ranges per affected product,
* all relevant metadata needed for publication and export.

The system should therefore allow both:

* a consolidated advisory view,
* and a structured per-product representation for machine-readable output.

### 5.3 Joomla Extension Identification

For Joomla-specific processing, each affected extension should be identifiable through:

* `type`
* `element`
* `folder`
* `client_id`

These fields are essential for mapping advisories to actual Joomla extensions in installations and inventory checks.

---

## 6. Suggested Functional Workflow

1. A reporter, developer, or user submits a potential issue via email or web form.
2. The VEL team reviews the report in the ticket system.
3. If the issue is valid, a backend record is created or updated in the Joomla 6.x component.
4. The editor fills in the vulnerability data, affected products, Joomla-specific identifiers, CWE information, and credits.
5. The system generates a CVE JSON draft for assignment and publication.
6. Once published, the advisory appears in:

   * the frontend list view,
   * the detail view,
   * the RSS feed,
   * and the JSON API.
7. External systems can query the JSON output to check whether installed extensions are affected.

---

## 7. Design Principles

The new VEL should follow these principles:

* **Centralization**: one authoritative place for Joomla extension vulnerabilities.
* **Efficiency**: reuse existing software for ticket handling whenever possible.
* **Simplicity**: keep the editor workflow as lean as possible.
* **Consistency**: structured data entry and structured outputs.
* **Automation-friendly**: machine-readable JSON and API support.
* **Community value**: clear information for web administrators, researchers, and extension developers.
* **Scalability**: support for multiple affected products per advisory and future extensions of the data model.

---

## 8. Expected Outcome

With this setup, the VEL becomes a practical and reliable security information platform for Joomla.

It will:

* improve visibility of known extension vulnerabilities,
* support responsible disclosure and coordination,
* reduce manual effort for the Joomla security team,
* and help site owners react faster to security issues in their installations.

---

## Appendix A: Example CVE JSON

```json
{
  "containers": {
    "cna": {
      "affected": [
        {
          "defaultStatus": "unaffected",
          "product": "Modals extension for Joomla",
          "vendor": "regularlabs.com",
          "versions": [
            {
              "status": "affected",
              "version": "1.0.0-15.0.0"
            }
          ]
        },
        {
          "defaultStatus": "unaffected",
          "product": "Tooltips extension for Joomla",
          "vendor": "regularlabs.com",
          "versions": [
            {
              "status": "affected",
              "version": "1.0.0-9.4.7"
            }
          ]
        },
        {
          "defaultStatus": "unaffected",
          "product": "Articles Anywhere Pro extension for Joomla",
          "vendor": "regularlabs.com",
          "versions": [
            {
              "status": "affected",
              "version": "1.0.0-18.0.2"
            }
          ]
        },
        {
          "defaultStatus": "unaffected",
          "product": "Users Anywhere Pro extension for Joomla",
          "vendor": "regularlabs.com",
          "versions": [
            {
              "status": "affected",
              "version": "1.0.0-1.2.7"
            }
          ]
        },
        {
          "defaultStatus": "unaffected",
          "product": "Modules Anywhere Pro extension for Joomla",
          "vendor": "regularlabs.com",
          "versions": [
            {
              "status": "affected",
              "version": "1.0.0-8.4.1"
            }
          ]
        }
      ],
      "descriptions": [
        {
          "lang": "en",
          "supportingMedia": [
            {
              "base64": false,
              "type": "text/html",
              "value": "Joomla Extension - regularlabs.com - XSS vectors in tag-provided inputs in various Regular Labs extensions - Tag-provided custom HTML, module content/title overrides and decoded modal or tooltip values could execute unsafe markup. A content author could inject JavaScript that ran in visitors’ browsers."
            }
          ],
          "value": "Joomla Extension - regularlabs.com - XSS vectors in tag-provided inputs in various Regular Labs extensions - Tag-provided custom HTML, module content/title overrides and decoded modal or tooltip values could execute unsafe markup. A content author could inject JavaScript that ran in visitors’ browsers."
        }
      ],
      "problemTypes": [
        {
          "descriptions": [
            {
              "cweId": "CWE-79",
              "description": "CWE-79 Improper Neutralization of Input During Web Page Generation ('Cross-site Scripting')",
              "lang": "en",
              "type": "CWE"
            }
          ]
        }
      ],
      "providerMetadata": {
        "dateUpdated": "2026-07-27T13:29:26.815Z",
        "orgId": "6ff30186-7fb7-4ad9-be33-533e7b05e586",
        "shortName": "Joomla"
      },
      "references": [
        {
          "tags": [
            "product"
          ],
          "url": "https://regularlabs.com/"
        }
      ],
      "source": {
        "discovery": "UNKNOWN"
      },
      "title": "Joomla Extension - regularlabs.com - XSS vectors in tag-provided inputs in various Regular Labs extensions",
      "x_generator": {
        "engine": "Vulnogram 0.1.0-dev"
      }
    }
  },
  "cveMetadata": {
    "assignerOrgId": "6ff30186-7fb7-4ad9-be33-533e7b05e586",
    "assignerShortName": "Joomla",
    "cveId": "CVE-2026-64795",
    "datePublished": "2026-07-22T20:41:19.007Z",
    "dateReserved": "2026-07-20T18:16:31.592Z",
    "dateUpdated": "2026-07-27T13:29:26.815Z",
    "state": "PUBLISHED"
  },
  "dataType": "CVE_RECORD",
  "dataVersion": "5.2"
}
```
