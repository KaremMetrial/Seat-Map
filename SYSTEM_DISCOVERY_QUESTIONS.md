# Seat Map System Discovery Questions

**Version**: 1.0  
**Date**: 2026-05-12  
**Based on**: System Specification

---

## EPIC 1: Template Management

### STORY 1.1 - Create Seat Map Template

**Questions:**

1. **What metadata should be stored for each venue template?** (Beyond canvas dimensions)

2. **Should templates support multiple venue types?** (Aircraft, theaters, stadiums)

3. **How should we handle template naming conflicts?**

4. **Do templates need version descriptions or changelogs?**

5. **Should we support template cloning/copying?**

---

### STORY 1.2 - Edit & Version Templates

**Questions:**

6. **What triggers a new template version?** (Any change vs major changes)

7. **How many versions should we keep?**

8. **Should users see a diff between versions?**

9. **Can template versions be tagged?** (e.g., "Production", "Draft")

10. **Should expired versions be archived or deleted?**

---

## EPIC 2: Seat Map Builder

### STORY 2.1 - Add Elements to Canvas

**Questions:**

11. **What element types are supported?** (seat, table, stage, entrance, text, shape)

12. **How should element properties be organized?** (data_json vs separate columns)

13. **Should we autosave element changes?**

14. **How do we handle element dependencies?** (e.g., table contains seats)

15. **Should elements have unique identifiers across templates?**

---

### STORY 2.2 - Move & Resize Elements

**Questions:**

16. **What are the minimum element dimensions?**

17. **Should elements snap to a grid?** (If so, what grid size?)

18. **How should we validate canvas boundaries?** (Allow partial, clamp, or reject?)

19. **Should rotation be limited to 90-degree increments?**

20. **How do we handle z-index conflicts?**

---

### STORY 2.3 - Parent/Child Hierarchy

**Questions:**

21. **What's the maximum nesting depth allowed?**

22. **Should parent elements inherit visibility?** (Hide parent = hide children)

23. **Can parents be moved without affecting children?**

24. **Should we support sibling ordering within parents?**

25. **How do we validate hierarchy cycles?**

---

## EPIC 3: Zones & Pricing

### STORY 3.1 - Create Zones

**Questions:**

26. **What zone properties are required?** (name, code, color, priority, base_price, capacity)

27. **Should zones have descriptions or notes?**

28. **How are zone colors validated?** (HEX only, or named colors?)

29. **Should zones support subcategories?** (VIP-Front, VIP-Back)

30. **Can zones be disabled or archived?**

---

### STORY 3.2 - Assign Elements to Zones

**Questions:**

31. **Can an element belong to multiple zones?**

32. **Should zone assignment be validated against template?**

33. **How do we handle bulk zone reassignment?**

34. **Should we show zone coverage statistics?**

35. **Can zone assignments be copied between templates?**

---

### STORY 3.3 - Pricing Engine

**Questions:**

36. **What pricing modifiers are supported?** (fixed amount, percentage, tier)

37. **Should pricing rules have effective dates?**

38. **How are service fees calculated?** (per seat, per transaction, percentage)

39. **Should pricing be cached or calculated on-demand?**

40. **What happens if zone base_price is missing?**

---

## EPIC 4: Booking Engine

### STORY 4.1 - Seat Availability

**Questions:**

41. **What seat states exist?** (available, locked, booked, blocked)

42. **Should we show a seat count per zone?**

43. **How often should availability be refreshed?**

44. **Should users see why seats are unavailable?**

45. **Can availability be filtered by seat type?**

---

### STORY 4.2 - Hold Seats Temporarily

**Questions:**

46. **What's the default hold duration?**

47. **Can hold duration be customized per event?**

48. **Should we notify users before hold expires?**

49. **What happens if user's session times out during hold?**

50. **Should holds be extendable?**

---

## EPIC 5: Real-Time System

### STORY 5.1 - Live Seat Updates

**Questions:**

51. **Which events trigger real-time updates?** (booking, cancellation, hold)

52. **Should we show a "someone just booked" notification?**

53. **How many concurrent WebSocket connections should we support?**

54. **Should we batch real-time updates?**

55. **What fallback exists if WebSocket fails?**

---

## EPIC 6: Compliance & Validation

### STORY 6.1 - Seat Area Validation

**Questions:**

56. **What are minimum seat dimensions?**

57. **Should we validate total venue area?**

58. **Should we check for overlapping seats?**

59. **Should validation be real-time or batch?**

60. **Should we show validation warnings or block saving?**

---

### STORY 6.2 - Aisle & Exit Validation

**Questions:**

61. **What's the minimum aisle width?**

62. **Should we validate all exits are accessible?**

63. **Should we check emergency equipment placement?**

64. **Should violations prevent publishing?**

65. **Should we support custom compliance rules?**

---

### STORY 6.3 - Accessibility Validation

**Questions:**

66. **What's the ratio of wheelchair seats to total capacity?**

67. **How close must wheelchair seats be to restrooms?**

68. **Should we validate evacuation routes?**

69. **Should accessibility alerts be dismissible?**

70. **Should we support ADA/WCAG compliance standards?**

---

## EPIC 7: Aircraft-Specific Questions

### STORY 7.1 - Aircraft Venue Creation

**Questions:**

71. **Should aircraft venues support multiple configurations?**

72. **Should we store aircraft specifications?** (max passengers, registration)

73. **Should aircraft templates be reusable across airlines?**

74. **Should we validate aircraft dimensions?**

75. **Should we support aircraft registration numbers?**

---

### STORY 7.2 - Cabin Classes & Zones

**Questions:**

76. **Should cabin classes be hierarchical?** (First → Business → Economy)

77. **Should we support mixed-class configurations?**

78. **Should cabin boundaries be enforced?**

79. **Should we show cabin occupancy percentages?**

80. **Should cabin pricing be configurable per flight?**

---

### STORY 7.3 - Premium Seat Features

**Questions:**

81. **Should we store seat amenities?** (lie-flat, private door)

82. **Should seat images be supported?**

83. **Should we show seat premium indicators?**

84. **Should we support seat upgrade paths?**

85. **Should we validate premium seat spacing?**

---

### STORY 7.4 - Aircraft Facilities

**Questions:**

86. **Should we categorize facilities?** (galley, lavatory, lounge)

87. **Should facilities have capacity limits?**

88. **Should we show facility locations on the map?**

89. **Should we validate emergency equipment placement?**

90. **Should we support multi-aircraft fleet management?**

---

## ADDITIONAL SYSTEM QUESTIONS

### Integration Points

91. **How should the seat map integrate with external BOS systems?**

92. **Should we support real-time BOS synchronization?**

93. **Should we cache BOS data locally?**

94. **What's the BOS data refresh frequency?**

95. **Should we support offline template editing?**

---

### Performance & Scale

96. **What's the largest aircraft template we need to support?** (Number of seats)

97. **Should we implement template chunking?**

98. **Should we support template previews?**

99. **Should we compress template data?**

100. **Should we implement template search/filter?**

---

**End of Discovery Questions**

