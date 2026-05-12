# Seat Map Creation - All Questions

**Version**: 1.0  
**Date**: 2026-05-12  
**Format**: Single consolidated file

---

## SECTION 1: TEMPLATE MANAGEMENT

### 1.1 Template Creation
1. What metadata should be stored for each venue template?
2. Should templates support multiple venue types?
3. How should we handle template naming conflicts?
4. Do templates need version descriptions or changelogs?
5. Should we support template cloning/copying?

### 1.2 Version Management
6. What triggers a new template version?
7. How many versions should we keep?
8. Should users see a diff between versions?
9. Can template versions be tagged?
10. Should expired versions be archived or deleted?

---

## SECTION 2: SEAT MAP BUILDER

### 2.1 Element Creation
11. What element types are supported?
12. How should element properties be organized?
13. Should we autosave element changes?
14. How do we handle element dependencies?
15. Should elements have unique identifiers?

### 2.2 Positioning & Styling
16. What are the minimum element dimensions?
17. Should elements snap to a grid?
18. How should we validate canvas boundaries?
19. Should rotation be limited to 90-degree increments?
20. How do we handle z-index conflicts?

### 2.3 Hierarchy
21. What's the maximum nesting depth allowed?
22. Should parent elements inherit visibility?
23. Can parents be moved without affecting children?
24. Should we support sibling ordering?
25. How do we validate hierarchy cycles?

---

## SECTION 3: ZONES & PRICING

### 3.1 Zone Management
26. What zone properties are required?
27. Should zones have descriptions or notes?
28. How are zone colors validated?
29. Should zones support subcategories?
30. Can zones be disabled or archived?

### 3.2 Zone Assignment
31. Can an element belong to multiple zones?
32. Should zone assignment be validated?
33. How do we handle bulk zone reassignment?
34. Should we show zone coverage statistics?
35. Can zone assignments be copied between templates?

### 3.3 Pricing Engine
36. What pricing modifiers are supported?
37. Should pricing rules have effective dates?
38. How are service fees calculated?
39. Should pricing be cached or calculated on-demand?
40. What happens if zone base_price is missing?

---

## SECTION 4: BOOKING ENGINE

### 4.1 Seat Availability
41. What seat states exist?
42. Should we show a seat count per zone?
43. How often should availability be refreshed?
44. Should users see why seats are unavailable?
45. Can availability be filtered by seat type?

### 4.2 Seat Holding
46. What's the default hold duration?
47. Can hold duration be customized per event?
48. Should we notify users before hold expires?
49. What happens if session times out during hold?
50. Should holds be extendable?

---

## SECTION 5: REAL-TIME SYSTEM

### 5.1 Live Updates
51. Which events trigger real-time updates?
52. Should we show a "someone just booked" notification?
53. How many concurrent WebSocket connections?
54. Should we batch real-time updates?
55. What fallback exists if WebSocket fails?

---

## SECTION 6: COMPLIANCE & VALIDATION

### 6.1 Seat Area Validation
56. What are minimum seat dimensions?
57. Should we validate total venue area?
58. Should we check for overlapping seats?
59. Should validation be real-time or batch?
60. Should we show warnings or block saving?

### 6.2 Aisle & Exit Validation
61. What's the minimum aisle width?
62. Should we validate all exits are accessible?
63. Should we check emergency equipment placement?
64. Should violations prevent publishing?
65. Should we support custom compliance rules?

### 6.3 Accessibility Validation
66. What's the ratio of wheelchair seats?
67. How close must wheelchair seats be to restrooms?
68. Should we validate evacuation routes?
69. Should accessibility alerts be dismissible?
70. Should we support ADA/WCAG standards?

---

## SECTION 7: AIRCRAFT-SPECIFIC

### 7.1 Aircraft Venues
71. Should aircraft venues support multiple configurations?
72. Should we store aircraft specifications?
73. Should aircraft templates be reusable across airlines?
74. Should we validate aircraft dimensions?
75. Should we support aircraft registration numbers?

### 7.2 Cabin Classes
76. Should cabin classes be hierarchical?
77. Should we support mixed-class configurations?
78. Should cabin boundaries be enforced?
79. Should we show cabin occupancy percentages?
80. Should cabin pricing be configurable per flight?

### 7.3 Premium Features
81. Should we store seat amenities?
82. Should seat images be supported?
83. Should we show seat premium indicators?
84. Should we support seat upgrade paths?
85. Should we validate premium seat spacing?

### 7.4 Aircraft Facilities
86. Should we categorize facilities?
87. Should facilities have capacity limits?
88. Should we show facility locations on the map?
89. Should we validate emergency equipment placement?
90. Should we support multi-aircraft fleet management?

---

## SECTION 8: INTEGRATION & PERFORMANCE

### 8.1 BOS Integration
91. How should the seat map integrate with BOS systems?
92. Should we support real-time BOS synchronization?
93. Should we cache BOS data locally?
94. What's the BOS data refresh frequency?
95. Should we support offline template editing?

### 8.2 Performance
96. What's the largest template we need to support?
97. Should we implement template chunking?
98. Should we support template previews?
99. Should we compress template data?
100. Should we implement template search/filter?

---

## SECTION 9: USER EXPERIENCE

### 9.1 Flow Selection
1. Which flow do users prefer - manual or auto?
2. Should we remember their last selection?
3. Do you have data on user preferences?

### 9.2 Map Display
4. Should the map fill the entire screen?
5. What information in introductory text?
6. Mobile: map first or zone list?
7. What info in hover popup?

### 9.3 Zoom & Navigation
8. What are min/max zoom levels?
9. Show mini-map always?
10. Allow click-drag panning?
11. Auto-fit all seats on load?

### 9.4 Filters
12. Which filters are most important?
13. Auto-apply or button click?
14. Show seat count per filter?
15. Remember filter preferences?

### 9.5 Mobile Experience
16. Filter panel slide-in or modal?
17. Filter count badge on mobile?
18. Swipe between map and zone list?

---

## SECTION 10: SUCCESS METRICS

1. **Target conversion rate?**
2. **How measure success?**
3. **Expected seat selection steps?** (< 3 clicks)
4. **Target mobile conversion?** (> 65%)
5. **Load time target?** (< 2 seconds)
6. **Error rate target?** (< 1%)

