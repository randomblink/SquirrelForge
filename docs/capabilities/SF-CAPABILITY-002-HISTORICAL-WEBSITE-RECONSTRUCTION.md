# SF-CAPABILITY-002 — Historical Website Reconstruction

**Status:** Defined; execution not yet demonstrated

## Purpose

Define the process for reconstructing publicly accessible portions of a historical website using archived sources such as the Internet Archive's Wayback Machine. The objective is to recreate publicly observable content, structure, and presentation while recording what was recovered, reconstructed, or unavailable.

## Scope

This capability applies when the requester owns the website or is authorized to reproduce it, publicly archived material is available, and the reconstruction records its evidence and limitations. It supports static reconstruction and CMS-based reconstruction, including WordPress.

It does not recover original databases, server configuration, authentication systems, private content, plugins or custom applications unless independently recreated, or functionality that was never publicly visible.

## Procedure

1. Define the original domain, capture date or range, subdomains, pages and assets, and whether the result is static or CMS-based.
2. Inventory archived URLs using available archive indexes such as the Wayback CDX API, recording URLs, timestamps, status, and missing resources.
3. Retrieve pages and assets while preserving directory structure where practical; record successful, missing, substituted, and failed resources.
4. Reconstruct page hierarchy, navigation, styling, media, templates, content, and visible functionality as applicable. Treat archived HTML as presentation evidence, not proof of the original application architecture.
5. Validate URL structure, navigation, titles, visible text, images, styling, responsive presentation, and links.
6. Classify each component as directly recovered, reconstructed from archived evidence, or replaced because evidence was unavailable.

## Limitations

Historical archives are not complete backups. They may omit authenticated content, dynamic behavior, JavaScript-generated interfaces, streaming media, server-side functionality, failed assets, or content excluded from collection. A reconstruction is therefore a documented historical recreation, not an exact copy of the original operational environment.

## Deliverables

- Reconstruction scope.
- Archived resource inventory.
- Capture dates.
- Download log.
- Reconstructed website.
- Missing-asset report.
- Validation report.
- Reconstruction notes covering assumptions, substitutions, and evidence boundaries.

## Demonstration status

This record defines the capability and its evidence requirements. No reconstruction execution has yet been recorded as a SquirrelForge demonstration.
