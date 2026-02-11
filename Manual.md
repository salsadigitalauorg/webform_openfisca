

#

# Rules as Code Webform OpenFisca Drupal module User manual

<!-- TOC -->

* [Rules as Code Webform OpenFisca Drupal module User manual](#rules-as-code-webform-openfisca-drupal-module-user-manual)
* [Course objectives](#course-objectives)
* [About Rules as Code](#about-rules-as-code)
* [The RaC process](#the-rac-process)
* [Our scenarios/use cases](#our-scenariosuse-cases)
* [About Drupal and Rules as Code](#about-drupal-and-rules-as-code)
* [About the OpenFisca API](#about-the-openfisca-api)
* [Analysing the API requirements](#analysing-the-api-requirements)
  * [1\. Entities](#1-entities)
  * [2\. Variables](#2-variables)
* [Creating a webform](#creating-a-webform)


* [Creating RaC content](#creating-rac-content)
  * [Creating results page (intro)](#creating-results-page-intro)
* [Redirection rules (intro)](#redirection-rules-intro)
* [Test the webform](#test-the-webform)
* [Tokens](#tokens)
* [Results blocks](#results-blocks)
* [Testing the demo webforms](#testing-the-demo-webforms)
  * [Exercise: testing the webform](#exercise-testing-the-webform)
* [Conclusion](#conclusion)
* [Further references](#further-references)
<!-- TOC -->

# Course objectives

By the end of this course you will be able to:

* Set up a webform for use in Rules as Codes builds (via the Webform OpenFisca Drupal module)
* Set up branching logic for the webform
* Set up results page(s)
* Create multiple blocks and show/ hide them as required based on responses from the OpenFisca API

# About Rules as Code

Rules As Code (RaC) takes legislation, regulations and policies and turns them into machine-readable code so they can be understood and interpreted by computers. RaC helps to reduce ambiguity, reduces the difficulty of interpretation and therefore makes it easier for citizens and organisations to comply with the rules. Importantly, it also leads to greater transparency in rules.

For a full list of benefits, see the OECD’s [Cracking the code: Rulemaking for humans and machines](https://www.oecd-ilibrary.org/deliver/3afe6ba5-en.pdf?itemId=%2Fcontent%2Fpaper%2F3afe6ba5-en&mimeType=pdf) (p.39 includes a benefits table).

Many governments around the world are exploring and implementing RaC — although it’s still an emerging field.

France was an early adopter, creating [OpenFisca](https://openfisca.org/en/), an open source rules engine, based on Python.

# The RaC process

At a high level, the Rules as Code process we use follows four stages:

![RaC Process](assets/rac-process.png "RaC Process")

In this user manual we focus on the final two stages: creating the Drupal webform and creating results pages (with blocks to display customised content).

# Our scenarios/use cases

This manual references two main scenarios/uses cases. The uses cases are:

* Employing a young person in the ACT (reduced scope)
* Disability allowance (reduced scope)

These two scenarios are used throughout our three training courses and manuals:

* Rules as Code — rules mapping
* Rules as Code — OpenFisca
* Rules as Code —Webform OpenFisca Drupal module

The ‘Employing a young person in the ACT’ scenario focuses on rules that apply to employers that employ people 17 years old or under.

The ‘Disability allowance’ example provides a simplified version of eligibility requirements for a disability allowance.

# About Drupal and Rules as Code

Drupal and OpenFisca can work together via the [Webform OpenFisca](https://www.drupal.org/project/webform_openfisca) Drupal module. Salsa Digital created this module to integrate OpenFisca with the Drupal CMS. The module extends webform functionality in a way that it can interact with the OpenFisca API. The module also has a custom RAC content type, which is used by content creators to create redirection rules.

# About the OpenFisca API

Before we start, we first need to understand the details from the OpenFisca API.
As an example, for this training manual we’re using a case where we are trying to find out if a person is eligible for disability allowance.

The sample payload for this can be as follows:

```
{
    "persons": {
        "personA": {
            "monthly_income": {
                "2024-10-25": "20"
            },
            "has_disability": {
                "2024-10-25": true
            },
            "requires_ongoing_support": {
                "2024-10-25": true
            },
            "requires_ongoing_supervision_or_treatment": {
                "2024-10-25": true
            },
            "disability_allowance_eligible": {
                "2024-10-25": null
            },
            "aus_citizen": {
                "2024-10-25": true
            },
            "disability_allowance_benefit": {
                "2024-10-25": null
            },
            "monthly_income_exceeds_limit": {
                "2024-10-25": null
            }
        }
    }
}
```

And the corresponding response could be:

```
{
    "persons": {
        "personA": {
            "aus_citizen": {
                "2024-10-25": true
            },
            "disability_allowance_benefit": {
                "2024-10-25": 200
            },
            "disability_allowance_eligible": {
                "2024-10-25": true
            },
            "has_disability": {
                "2024-10-25": true
            },
            "monthly_income": {
                "2024-10-25": "20"
            },
            "monthly_income_exceeds_limit": {
                "2024-10-25": false
            },
            "requires_ongoing_supervision_or_treatment": {
                "2024-10-25": true
            },
            "requires_ongoing_support": {
                "2024-10-25": true
            }
        }
    }
}

```

# Analysing the API requirements

The payload needs to be analysed and split into many parts, including:

1. Entities
2. Variables

## 1\. Entities

In this example, there is 1 entity: **Person**

Definitions of these entities can be found in the API when we go to **/entities**.

In our example, the definitions of **person** can be seen in the code below.

```
person": {
    "description": "An individual. The minimal legal entity on which a legislation might be applied.",
    "documentation": "Variables like 'salary' and 'income_tax' are usually defined for the entity 'Person'.\n\nUsage:\nCalculate a variable applied to a 'Person' (e.g. access the 'salary' of a specific month with person(\"salary\", \"2017-05\")).\nCheck the role of a 'Person' in a group entity (e.g. check if a the 'Person' is a 'first_parent' in a 'Household' entity with person.has_role(Household.FIRST_PARENT)).\n\nFor more information, see: https://openfisca.org/doc/coding-the-legislation/50_entities.html",
    "plural": "persons"
  }

```

## 2\. Variables

The variables that are being sent are:

1. Australian citizen or resident? :- aus\_citizen

2. Monthly income: monthly\_income

3. Have disability?: has\_disability

4. Require ongoing support: requires\_ongoing\_support

5. Requires ongoing supervision or treatment: requires\_ongoing\_supervision\_or\_treatment

6. Eligible for disability allowance? :  disability\_allowance\_eligible
   Note:  This is sent as null/ blank but has a return value of true/ false. This is one of the return variables.

7. Benefit quantity for eligibility allowance: disability\_allowance\_benefit
   Note: This is sent as null/ blank but has a return value of total benefits, if applicable. This is one of the return variables.

8. Does the monthly income exceed the limit: monthly\_income\_exceeds\_limit
   Note: This is sent as null/ blank but returns 1 if the monthly salary exceeds a limit defined in OpenFisca. This is one of the return variables.

**These are the variables that need to be configured in the webform.**

# Creating a webform

Once you’ve analysed the API, you’re ready to start the process of creating the webform.

1. Navigate to /admin/structure/webform

2. Click "Add webform"
   ![](assets/create-webform-1.png)

3. Type "Demo" as the title of the webform
   ![](assets/create-webform-2.png)

4. Click "Save"
   ![](assets/create-webform-3.png)

5. Now we need to add some settings to the webform. Click "Settings"
   ![](assets/create-webform-4.png)

6. To add the OpenFisca handler, click "Emails / Handlers"
   ![](assets/create-webform-5.png)

7. Click "Add handler"
   ![](assets/create-webform-6.png)

8. Click "Add handler" against the OpenFisca Journey handler - this step will ensure that the webform goes through OpenFisca processing. ![](assets/create-webform-7.png)

9. Click Save
   ![](assets/create-webform-8.png)

10. Now we need to add details of the OpenFisca API. Click "General"
    ![](assets/create-webform-9.png)

11. Check the "Enable OpenFisca RaC integration" checkbox

    ![](assets/create-webform-10.png)


12. When you click on this checkbox, you will see a pop-up:

    ![](assets/pop-up.png)

    This pop-up reminds you to consider what information your form is collecting and if it has any privacy implications. For example, are you collecting any personally identifiable information such as someone’s name? Webform submissions are automatically saved in Drupal. If your webform *does* contain questions with potential privacy implications you may need to disable the saving of submissions.

You can disable  the saving of submission data in the “General Settings” on the same page.

  ![](assets/create-webform-11.png)

13. **Note:** You can also enable/ disable debug for this webform from this screen.
![](assets/create-webform-12.png)

When **Enable debug mode** is checked, the result page will show debug information like the screenshot below:
 ![](assets/debug-1.png)
This information can help when you’re testing to see if the webform is returning the result you expect.

When **Log OpenFisca calculation** is checked, this information is sent for DB logging.

14. Click the "OpenFisca API endpoint" field
    ![](assets/create-webform-13.png)

15. Type the API endpoint. Also, add the Authorisation header in case the endpoint is protected.

16. Now we need to add the return values that we need from the API.
     Click the "The keys for the return value" field.
    ![](assets/create-webform-14.png)

Add the keys for the return variables, comma-separated. The return variables
need to be fully qualified.

**Please note that we mentioned some of the return variables earlier. However, any variable used to determine block visibility (which we will discuss later) must be added to this section.**

In our case, (for now) they are:

```
persons.personA.disability_allowance_eligiblepersons.personA.disability_allowance_benefitpersons.personA.monthly_income_exceeds_limit
```

We will come back later and update this value once we start working on block visibility.
![](assets/create-webform-15.png)

17. Click **Save**.
    ![](assets/create-webform-16.png)

18. Now we need to configure the webform to redirect to a page. Click "Confirmation"
    ![](assets/create-webform-17.png)

19. Click the "URL (redirects to a custom path or URL)" field.
    ![](assets/create-webform-18.png)

20. Click the "Confirmation URL" field.
    ![](assets/create-webform-19.png)

21. Add any existing URL on the site, e.g. I have added "/about-umami"
    ![](assets/create-webform-20.png)

22. Save the settings.

23. Now let's start building the webform. We will start by creating the webform as usual, but we will need to "attach" each field to an OpenFisca variable.

24. Click "Build"
    ![](assets/build-1.png)

25. Click "Add element"
    ![](assets/build-2.png)

26. The first field we want to add is “Are you an AUS citizen or resident?”. We will add this as a Select option.
    ![](assets/build-3.jpeg)

    ![](assets/build-4.jpeg)

27.  Let’s name the field aus\_citizen\_or\_permanent\_resident
    ![](assets/build-5.jpeg)

28. Add the options for the field. Please note that in this case, we are using prefix and suffix for the field to improve the UX of the webform.
     ![](assets/build-6.png)

29. Now, we need to associate it to an OpenFisca variable. Scroll down and click this dropdown.
    ![](assets/build-7.png)

30. When you click the dropdown, you will see a list of variables defined in the OpenFisca API, which was defined earlier.
    ![](assets/build-8.png)

31. Select the appropriate variable from the dropdown\
    ![](assets/build-11.jpeg)

32. Next, we need to enter the entity name. Click the "Fisca entity key" field.\
    ![](assets/build-12.png)

33. Type PersonA
    ![](assets/build-13.jpeg)

34. Next we go ahead and add all the other variables to the webform.

35. Now we need to add the return variables as hidden fields.
     So we search for "hidden" and click "Add element"
    ![](assets/build-14.png)

36. This is the return variable if the person qualifies for disability allowance or not.
    ![](assets/build-15.png)

37. We will associate the OpenFisca variable to this as well:
    ![](assets/build-16.png)


38. We will follow the same process and add the 2 more return variables we need.
    disability\_allowance\_benefit and monthly\_income\_exceeds\_limit

39. You may or may not want to add conditional visibility options for the form. E.g. if the user says that they are NOT an AUS citizen or resident, we might want to show them a “You are not eligible” message and not show them the Submit button. For this, you can add a markup like this
![](assets/build-17.png)

And configure conditions like this: ![](assets/build-18.png)

40. The form is now created. Below is a screenshot of what it looks like in the frontend of the website.
    ![](assets/build-19.png)

##

##

[//]: # (## Exercise: creating a webform)

[//]: # ()
[//]: # (Let’s create a similar webform, for a different use case. ACT employing young people. [https://www.act.gov.au/community/youth/employing-young-people]&#40;https://www.act.gov.au/community/youth/employing-young-people&#41;)

[//]: # ()
[//]: # (Details:)

[//]: # ()
[//]: # (1. OpenFisca API endpoint is [https://training-rac.salsadev.au/]&#40;https://training-rac.salsadev.au/&#41;)

[//]: # (2. Return variables needed are)

[//]: # (   1. persons.personA.act\_child\_work\_compliant)

[//]: # (   2. persons.personA.act\_work\_hours\_over)

[//]: # (3. Questions:)

[//]: # ()
[//]: # (| No. | Question | Openfisca variable | Next steps |)

[//]: # (| :---- | :---- | :---- | :---- |)

[//]: # (| 1 | How old is the child? | child\_age | Go to Q2 If age \<=14, show Q4 and 5 |)

[//]: # (| 2 | The child \<is/is not\> currently at school. | child\_currently\_at\_school | If true, show Q3 |)

[//]: # (| 3 | The work \<is/is not\> outside of school hours? | child\_work\_outside\_school\_hours |  |)

[//]: # (| 4 | How many hours per week will the child be working? | child\_weekly\_work\_hours |  |)

[//]: # (| 5 | There \<are/are not\> adequate supervision and work and safety standards in place. | child\_adequate\_supervision\_and\_work\_safety |  |)

# Creating RaC content

The next step is to create Results page(s) now. There are two main ways to show the results returned from the OpenFisca calls — using different result pages and using blocks.

## Creating results page (intro)

In our use case, the user can either be eligible or not eligible for disability allowance. So we will create pages for both options.

1. Create content of any type - as per your website. Mostly “Page”
   ![](assets/content-1.jpeg)
   ![](assets/content-2.jpeg)
   ![](assets/content-3.jpeg)
2. Add an relevant title (You are eligible) and accompanying content and then click **Save**.
   ![](assets/content-4.jpeg)
3. Similarly, create another page for not eligible with a title (You are not eligible) and relevant content, and then click **Save**.

# Redirection rules (intro)

The next step is to tell the system that if the return value is 1  go to the “You are eligible” page and if the return value is 0  go to the “You are not eligible” page.

For this, we have a special content type called RAC.

This is how we use it:

1. Create content of the type RAC
![](assets/redirect-1.jpeg)
![](assets/redirect-2.jpeg)
![](assets/redirect-3.jpeg)
2. Add an appropriate title, like “RAC for Disability Allowance”
3. Associate the node to the correct webform ![](assets/redirect-4.jpeg)
4. Now start adding rules. If the return variable is 1, go to the page You are eligible. ![](assets/redirect-5.jpeg)![](assets/redirect-6.jpeg)![](assets/redirect-7.jpeg)![](assets/redirect-8.jpeg)![](assets/redirect-9.png)
5. If the return variable is 0, go to the page You are not eligible. ![](assets/redirect-10.png)
6. Save the node.

# Test the webform

We are now in a position to test the whole flow.

Go to the webform and input different answers. On submit, the webform sends out information to the OpenFisca API, and receives a response with calculated values.

We have enabled debug for our form, so you can see the request and the response values.

Payload:

![](assets/debug-2.png)

Response:![](assets/debug-3.png)

Results:

![](assets/debug-4.png)

You will also see that you will be redirected to different result pages.

The content of the results page can be customised as required.

# Tokens

OpenFisca also has a concept of **Parameters**. A parameter is a property of the legislation that changes over time. Unlike a variable, a parameter is not specific to a specific entity (e.g. person, household).

In our use case, one of the parameters would be the disability allowance benefit. ![][image60]

The module allows us to expose certain parameters as tokens as well, so that they can be shown in the result pages. This can improve the user experience by giving the user as much information as possible when showing results.

You will need to enable the module [Token Filter](https://www.drupal.org/project/token_filter) and then add tokens on the page to show more details.

For example, if we want to show the value of the disability allowance benefit, we can follow these steps:

1. Go to the form Settings page
2. In the Third party settings, at the very bottom, there is a field for OpenFisca parameter tokens
3. Add the value ‘disability\_allowance\_benefit’ to that field
   ![](assets/tokens-1.png)
4. Save the settings.
5. Check that the token is available. Go to /admin/help/token. You should be able to see this:
    ![](assets/tokens-2.png)

Now, we can add something like this to the content:

![](assets/tokens-3.png)

As you can see, we are using this token here.

```
$[webform_openfisca:wo_params:disability_allowance_checker:disability_allowance_benefit]
```

[//]: # (## Exercise: creating results pages and redirection rules for the new webform)

[//]: # ()
[//]: # (1. Create  2 pages — you are compliant, and you are not compliant)

[//]: # (2. Create a RAC page for redirection based on the value of persons.personA.act\_child\_work\_compliant)

# Results blocks

Imagine a scenario where the person is not eligible for disability allowance, and we want to provide them with more information about *why* they’re not eligible.

Some scenarios:

1. You are not eligible because your income exceeds a minimum amount (defined as a parameter in OpenFisca). Response from Openfisca would have these values:
   1. disability\_allowance\_eligible=0
   2. monthly\_income\_exceeds\_limit=1
2. You are not eligible because you are not disabled.
   1. disability\_allowance\_eligible=0
   2. has\_disability=0
3. And so on…

Let's go back to our form and submit it. When you check the results page and check the debug information, you will see that there is a long response. We will be using the variables in the response payload to configure blocks and their visibility.

The Webform OpenFisca module exposes a paragraph called **Block RAC Elements**.  Once this field is added to any block type, that block type is ready to be used as a result block that can have its visibility defined.

So, before we start this exercise, the website builder/developer for the website needs to add a new field of the type Entity Reference Revision in one of your block types and point it to the Block RaC Elements paragraph type. The chosen block type will then have a field like this:

![](assets/block-1.png)

This points to the paragraph **Block RAC Elements.**
![](assets/block-2.png)



We are now ready to create our blocks.

Let’s start with use case 1 mentioned above:

You are not eligible because your income exceeds a minimum amount (defined as a parameter in OpenFisca). Response from OpenFisca would have these values:

1. disability\_allowance\_eligible=0
   2. monthly\_income\_exceeds\_limit=1

**Note**:

Both of these variables have already been added as return variables when we were configuring the webform settings, so no changes need to be made to the webform settings. If any of these had not been added, then we would need to go back to the webform settings page and add them in the field here:

![](assets/block-3.png)

For block creation you can follow the steps below.

1. Create a content block![](assets/block-4.jpeg)

2. Click on the Block type with the Block RAC Element field — in our case it’s ‘Component’
  ![](assets/block-5.jpeg)

  ![](assets/block-6.png)

3. Add a contextual title, which will help you when placing the block
   ![](assets/block-7.png)
4. Add content to the block![](assets/block-8.png)
5. Now we need to add the rules for block visibility. First, add the webform that we configured.
   ![](assets/block-9.png)

6. We have just 2 conditions, and they’re both ‘AND’ conditions. So we will enter them like this
   ![](assets/block-10.png)

   **Note:**

   The variables need to be fully qualified. So persons.personA.disability\_allowance\_eligible and not disability\_allowance\_eligible

   This interface can cater for a complex set of conditions, when we want to check for operators other than \=
   ![](assets/block-11.png)

   We can add multiple conditions, which need to be an ORed or XORed (instead of AND)

   ![](assets/block-12.png)

   We can also add multiple sets of conditions, and use AND/ OR/ XOR between them.
   ![](assets/block-13.png)

7. Save the Block.
8. Now, let’s place the block. Go to Block layout![][image77]

![](assets/block-14.jpeg) ![](assets/block-15.jpeg)

9. Find the block and place it in Content region ![](assets/block-16.jpeg) ![](assets/block-17.jpeg)
10. Add a filter for pages
    ![](assets/block-18.jpeg)

11. Click on Save block. Also, fix the order of the block. In real-world cases, you may want multiple blocks displaying, so you need to order them in the most logical way.
12. Let’s test this out now
    ![](assets/block-19.png)

![](assets/block-20.png)

As you can see in the screenshot above, the ‘Income exceeds limit’ block we added is showing directly after the results content of ‘You are not eligible’.

[//]: # (## Exercise: creating results blocks)

[//]: # ()
[//]: # (1. Create a block for Not compliant \- because of hours needing to be outside of school hours.)

[//]: # (2. Place the block on the content page that you created for “Not compliant”)

[//]: # (3. The query parameters to check would be:)

[//]: # (   1. act\_child\_work\_compliant=false)

[//]: # (   2. act\_work\_hours\_over=true)

[//]: # (   3. child\_adequate\_supervision\_and\_work\_safety=true)

# Testing the demo webforms

Once you’ve created the webform it’s time for testing (quality assurance). Part of the early business analyst work is to create test cases that the OpenFisca developers use when writing the code. These test cases can be re-used by adding the inputs to the frontend webform.

Below is a screenshot of test cases prepared for the ACT.

![](assets/test-1.png)

As you can see, we have inputs that cover the child’s age, whether they’re currently at school, if the work is outside the school hours, etc. To test the webform, you can enter these values directly into the form, and confirm that the correct output and block(s) are displayed.

Likewise our other example, disability allowance, also has test cases.

![](assets/test-2.png)

In the above example, we’re adding in a salary and then different true/false scenarios. Let’s take a look at two examples:

1. aus\_citizen=true \+ salary= $400 \+ has\_disability=true \+ requires\_ongoing\_support=true \+ requires\_ongoing\_supervision\_or\_treatment=true
2. aus\_citizen=true \+ salary= $500.15 \+ has\_disability=true \+ requires\_ongoing\_support=false \+ requires\_ongoing\_supervision\_or\_treatment=true

Add in the required values to the webform and then click on Submit.

![](assets/test-3.png)
We’re expecting a result of **eligible** and a dollar figure of $200.
![](assets/test-4.png)

## Exercise: testing the webform

Now test the second scenario from above in the webform. You’re expecting a result of Not eligible with the ‘income too high’ block.

# Conclusion

Rules as Code is a powerful tool for government and citizens. Ideally, RaC would be developed at the same time as the policy itself and can be used to model the impact of policy.

The webform and results configuration can be done by advanced content editors or Drupal developers. Combining OpenFisca and Drupal represents one method to get from legislation/rules to code.


# Further references

To find out more about Rules as Code and see examples and resources visit
[https://www.racguild.org/resources](https://www.racguild.org/resources)

You can also become a member of the Guild (for free) and find out more about RaC through the monthly townhall events.
