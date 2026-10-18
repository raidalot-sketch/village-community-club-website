# Event posters and Village Club concerts

Original public Facebook posters were downloaded from the photo viewer at the owner's request:

- Pizza: https://www.facebook.com/photo/?fbid=1095407899918971&set=a.130840999709004
- Fair: https://www.facebook.com/photo/?fbid=10165334655979604&set=gm.944612064938876

The existing upload pipeline processes images into WebP in private storage. Approved media is served through media.php. Event detail pages show the full cover once; homepage cards show a thumbnail. No source images, cookies, credentials or expiring CDN links belong in Git.

The separate Nettlebed Folk Club Facebook page, https://www.facebook.com/NettlebedFolkClub, links its official website at https://nettlebedfolkclub.co.uk/. Its homepage was updated 16 October 2026 and identifies the venue as The Village Club, High Street, Nettlebed, RG9 5DD. Cross-checked its programme PDF at https://nettlebedfolkclub.co.uk/programme/index.html and the revised dates page, which reports no revised dates at present.

| Date | Concert | Published ticket price |
| --- | --- | --- |
| 19 October 2026 | Chris While & Julie Matthews, support by Megan Henwood | £20 |
| 2 November 2026 | Ímar | £22 |
| 9 November 2026 | Bob Fox & Stu Luckley | £19 |
| 23 November 2026 | Peter Knight’s Gigspanner | £22 |
| 30 November 2026 | Megan Henwood & Steve Knightley, special guest musicians | £24 |
| 7 December 2026 | Jackie Oates & John Spiers — Christmas Show | £19 |
| 14 December 2026 | St Agnes Fountain | £25 |

All seven shows start at 19:30; doors open by 19:00. End times are not published: keep end_time NULL, display the start only and omit a speculative DTEND from ICS exports. Booking and availability: https://nettlebedfolkclub.co.uk/booking/index.php ; published telephone numbers 01628 636620 / 01628 603670, 10:00–20:00. No January concert appears in the current programme.

Keep the Christmas fair featured among three distinct upcoming homepage activities. Add verified concerts through the normal submission/approval functions, without social sharing or notifications.

Live acceptance completed on 18 October 2026: pizza and fair posters approved through attachment revisions (media IDs 4 and 5), served anonymously as WebP and displayed once on their detail pages. Seven concerts published as event IDs 3–9. The homepage shows the next concert, pizza night and featured fair, with both posters. November and December concert listings were verified in Chrome; the December calendar download omits unconfirmed finish times for the two concerts. Existing local tests for cover privacy, featured ordering, day selection and recurrence passed; a scoped source review found no material issue. Source/database backups preceded deployment and import. No new Facebook/Instagram delivery requests or notifications were created.
